<?php

namespace App\Actions;

use App\Models\CreditEntry;
use App\Models\CreditGrant;
use App\Models\PackageProduct;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageStudioCredits
{
    /** @param array<string, mixed> $attributes */
    public function grant(User $actor, array $attributes): CreditGrant
    {
        Gate::forUser($actor)->authorize('studio-credits');
        $data = Validator::make($attributes, [
            'operation_key' => ['required', 'uuid'],
            'user_id' => ['required', 'integer'],
            'package_product_id' => ['required', 'integer'],
            'source' => ['required', Rule::in(['demo', 'external_purchase', 'complimentary'])],
            'source_reference' => ['nullable', 'required_if:source,external_purchase', 'string', 'max:255'],
            'reason' => ['nullable', 'required_if:source,complimentary', 'string', 'max:2000'],
            'tenant_id' => ['missing'], 'credits' => ['missing'],
            'expires_at' => ['missing'], 'created_by_user_id' => ['missing'],
        ], [
            'source_reference.required_if' => 'Vanjska kupnja zahtijeva referencu računa ili evidencije.',
            'reason.required_if' => 'Besplatna dodjela zahtijeva razlog.',
        ])->validate();

        return DB::transaction(function () use ($actor, $data): CreditGrant {
            $tenant = $this->lock($actor);
            $existing = CreditGrant::query()->where('tenant_id', $tenant->id)->where('operation_key', $data['operation_key'])->first();
            if ($existing) {
                foreach (['user_id', 'package_product_id', 'source', 'source_reference', 'reason'] as $field) {
                    if ((string) $existing->getAttribute($field) !== (string) ($data[$field] ?? '')) {
                        throw ValidationException::withMessages(['operation_key' => 'Ovaj ključ već pripada drukčijoj dodjeli.']);
                    }
                }

                return $existing;
            }
            $user = User::query()->where('tenant_id', $tenant->id)->lockForUpdate()->whereKey($data['user_id'])->firstOrFail();
            $product = PackageProduct::query()->where('tenant_id', $tenant->id)->with('trainingTypes')->lockForUpdate()->whereKey($data['package_product_id'])->firstOrFail();
            if (! $user->is_active || $user->role !== 'customer') {
                throw ValidationException::withMessages(['user_id' => 'Odaberite aktivnog polaznika ovog studija.']);
            }
            if ($product->archived_at || $product->trainingTypes->isEmpty() || $product->trainingTypes->contains(fn ($type): bool => $type->archived_at !== null)) {
                throw ValidationException::withMessages(['package_product_id' => 'Odaberite aktivan paket s aktivnim vrstama treninga.']);
            }
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            $grant = new CreditGrant;
            $grant->forceFill([
                'user_id' => $user->id, 'package_product_id' => $product->id,
                'created_by_user_id' => $actor->id, 'source' => $data['source'],
                'source_reference' => $data['source_reference'] ?? null, 'reason' => $data['reason'] ?? null,
                'operation_key' => $data['operation_key'], 'granted_at' => $now, 'valid_from' => $now,
                'expires_at' => $now->setTimezone($tenant->timezone)->addDays($product->validity_days)->utc(),
                'credits' => $product->credits,
                'product_snapshot' => [...$product->only(['name', 'price_cents', 'currency', 'credits', 'validity_days']), 'timezone' => $tenant->timezone],
            ])->save();
            $grant->trainingTypes()->sync($product->trainingTypes->modelKeys());
            $entry = new CreditEntry;
            $entry->forceFill([
                'credit_grant_id' => $grant->id, 'created_by_user_id' => $actor->id,
                'kind' => 'grant', 'amount' => $grant->credits, 'occurred_at' => $now,
                'operation_key' => 'grant:'.$data['operation_key'],
            ])->save();
            $this->audit($actor, $entry);

            return $grant;
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function adjust(User $actor, CreditGrant $grant, array $attributes): CreditEntry
    {
        Gate::forUser($actor)->authorize('studio-credits');
        $data = Validator::make($attributes, [
            'operation_key' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'between:-1000,1000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:2000'], 'tenant_id' => ['missing'],
        ])->validate();

        return DB::transaction(function () use ($actor, $grant, $data): CreditEntry {
            $tenant = $this->lock($actor);
            $grant = CreditGrant::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($grant->id);
            $existing = CreditEntry::query()->where('tenant_id', $tenant->id)->where('operation_key', 'adjust:'.$data['operation_key'])->first();
            if ($existing) {
                if ($existing->credit_grant_id !== $grant->id || $existing->amount !== (int) $data['amount'] || $existing->reason !== $data['reason']) {
                    throw ValidationException::withMessages(['operation_key' => 'Ovaj ključ već pripada drukčijoj korekciji.']);
                }

                return $existing;
            }
            $balance = (int) $grant->entries()->sum('amount');
            $unfunded = (int) $grant->bookings()->where('status', 'confirmed')->whereDoesntHave('entries', fn ($query) => $query->where('kind', 'debit'))->sum('credits_used');
            if ($balance + (int) $data['amount'] < $unfunded) {
                throw ValidationException::withMessages(['amount' => 'Korekcija ne smije stvoriti negativan saldo ili oduzeti rezervirane kredite.']);
            }
            $entry = new CreditEntry;
            $entry->forceFill([
                'credit_grant_id' => $grant->id, 'created_by_user_id' => $actor->id,
                'kind' => 'adjustment', 'amount' => (int) $data['amount'], 'reason' => $data['reason'],
                'occurred_at' => now(), 'operation_key' => 'adjust:'.$data['operation_key'],
            ])->save();
            $this->audit($actor, $entry);

            return $entry;
        }, 3);
    }

    private function lock(User $actor): Tenant
    {
        $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
        abort_unless($tenant->status === 'active', 404);
        $freshActor = User::query()->where('tenant_id', $tenant->id)->findOrFail($actor->id);
        Gate::forUser($freshActor)->authorize('studio-credits');

        return $tenant;
    }

    private function audit(User $actor, CreditEntry $entry): void
    {
        $audit = new TenantAuditLog([
            'action' => 'credits.'.$entry->kind, 'subject_type' => 'credit_grants',
            'subject_id' => $entry->credit_grant_id,
            'changes' => $entry->only(['id', 'amount', 'reason', 'operation_key']),
        ]);
        $audit->actor()->associate($actor);
        $audit->save();
    }
}
