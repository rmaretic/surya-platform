<?php

namespace App\Actions;

use App\Models\BookingRuleVersion;
use App\Models\InstructorProfile;
use App\Models\PackageProduct;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageStudioCatalog
{
    public const array RULE_DEFAULTS = [
        'minimum_notice_minutes' => 120,
        'booking_horizon_days' => 56,
        'group_cancellation_minutes' => 720,
        'private_cancellation_minutes' => 1440,
        'studio_refund_validity_days' => 7,
    ];

    /** @param array<string, mixed> $attributes */
    public function save(User $actor, TrainingType|Room|InstructorProfile|PackageProduct $record, array $attributes): void
    {
        $ability = $record instanceof PackageProduct ? 'studio-pricing' : 'studio-catalog';
        Gate::forUser($actor)->authorize($ability);
        if ($actor->role !== 'owner' && array_intersect(array_keys($attributes), ['price_cents', 'credits', 'validity_days', 'rules', 'rules_snapshot', 'booking_rule_version_id', ...array_keys(self::RULE_DEFAULTS)]) !== []) {
            abort(403);
        }

        DB::transaction(function () use ($actor, $record, $attributes, $ability): void {
            $tenant = $this->lockTenant();
            Gate::forUser($actor)->authorize($ability);
            if ($record->exists) {
                $record = $record->newQuery()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($record->id);
            }

            $rules = match (true) {
                $record instanceof TrainingType => [
                    'name' => ['required', 'string', 'max:255'],
                    'mode' => ['required', Rule::in(['group', 'private'])],
                    'duration_minutes' => ['required', 'integer', 'between:1,480'],
                    'capacity' => ['required', 'integer', 'between:1,500'],
                    'description' => ['nullable', 'string', 'max:5000'],
                ],
                $record instanceof Room => [
                    'name' => ['required', 'string', 'max:255'],
                    'capacity' => ['required', 'integer', 'between:1,500'],
                ],
                $record instanceof InstructorProfile => [
                    'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenant->id)->whereIn('role', ['owner', 'manager', 'instructor']), Rule::unique('instructor_profiles', 'user_id')->where('tenant_id', $tenant->id)->ignore($record)],
                    'display_name' => ['required', 'string', 'max:255'],
                    'biography' => ['nullable', 'string', 'max:5000'],
                ],
                $record instanceof PackageProduct => [
                    'name' => ['required', 'string', 'max:255'],
                    'price_cents' => ['required', 'integer', 'between:1,10000000'],
                    'credits' => ['required', 'integer', 'between:1,1000'],
                    'validity_days' => ['required', 'integer', 'between:1,3650'],
                    'training_type_ids' => ['required', 'array', 'min:1', 'max:100'],
                    'training_type_ids.*' => ['required', 'integer', 'distinct', Rule::exists('training_types', 'id')->where('tenant_id', $tenant->id)],
                ],
            };
            $data = Validator::make($attributes, [
                ...$rules,
                'is_active' => ['required', 'boolean'],
                'tenant_id' => ['missing'],
                'archived_at' => ['missing'],
            ], [
                'user_id.exists' => 'Odaberite aktivnog člana osoblja ovog studija.',
                'user_id.unique' => 'Ovaj korisnik već ima instruktorski profil. Uredite postojeći profil.',
                'training_type_ids.*.exists' => 'Odaberite vrste treninga ovog studija.',
                'tenant_id.missing' => 'Studio se određuje domenom.',
            ])->validate();

            if ($record instanceof TrainingType) {
                if ($data['mode'] === 'private' && (int) $data['capacity'] !== 1) {
                    throw ValidationException::withMessages(['capacity' => 'Privatni trening ima kapacitet jedan.']);
                }
                if ($record->exists && $record->mode !== $data['mode']) {
                    throw ValidationException::withMessages(['mode' => 'Način postojeće vrste ne mijenja se. Dodajte novu vrstu treninga.']);
                }
            }

            if ($record instanceof InstructorProfile) {
                if ($record->exists && $record->user_id !== (int) $data['user_id']) {
                    throw ValidationException::withMessages(['user_id' => 'Postojeći profil ostaje vezan uz izvornog korisnika.']);
                }
                if (! $data['is_active']) {
                    app(EnsureInstructorCanDeactivate::class)->handle($record);
                }
                $user = User::query()->where('tenant_id', $tenant->id)->whereKey($data['user_id'])->firstOrFail();
                if ((! $record->exists || $data['is_active']) && ! $user->is_active) {
                    throw ValidationException::withMessages(['user_id' => 'Odaberite aktivnog člana osoblja ovog studija.']);
                }
                $record->user()->associate($user);
                unset($data['user_id']);
            }

            if ($record instanceof Room && $record->exists && $record->sessions()->whereIn('status', ['draft', 'published'])
                ->where('ends_at', '>', now())->where('capacity', '>', $data['capacity'])->exists()) {
                throw ValidationException::withMessages(['capacity' => 'Kapacitet prostora ne može biti manji od kapaciteta njegovih budućih termina.']);
            }

            $typeIds = $data['training_type_ids'] ?? [];
            $data['archived_at'] = $data['is_active'] ? null : ($record->archived_at ?? now());
            unset($data['is_active'], $data['training_type_ids']);
            $before = $record->exists ? $record->only(array_keys($data)) : [];
            $record->fill($data)->save();
            if ($record instanceof PackageProduct) {
                $record->trainingTypes()->sync($typeIds);
            }
            $this->audit($actor, 'catalog.saved', $record->getTable(), $record->id, ['before' => $before, 'after' => $record->only(array_keys($data))]);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function createRules(User $actor, array $attributes): void
    {
        Gate::forUser($actor)->authorize('studio-pricing');
        $data = Validator::make($attributes, [
            'minimum_notice_minutes' => ['required', 'integer', 'between:1,525600'],
            'booking_horizon_days' => ['required', 'integer', 'between:1,365'],
            'group_cancellation_minutes' => ['required', 'integer', 'between:1,525600'],
            'private_cancellation_minutes' => ['required', 'integer', 'between:1,525600'],
            'studio_refund_validity_days' => ['required', 'integer', 'between:7,365'],
            'tenant_id' => ['missing'],
            'version' => ['missing'],
            'created_by_user_id' => ['missing'],
        ])->validate();
        if ((int) $data['minimum_notice_minutes'] >= (int) $data['booking_horizon_days'] * 1440) {
            throw ValidationException::withMessages(['minimum_notice_minutes' => 'Minimalna najava mora biti kraća od horizonta rezervacije.']);
        }

        DB::transaction(function () use ($actor, $data): void {
            $tenant = $this->lockTenant();
            Gate::forUser($actor)->authorize('studio-pricing');
            $rules = new BookingRuleVersion;
            foreach ($data as $field => $value) {
                $rules->setAttribute($field, $value);
            }
            $rules->version = max(0, (int) BookingRuleVersion::query()->where('tenant_id', $tenant->id)->max('version')) + 1;
            $rules->creator()->associate($actor);
            $rules->save();
            $this->audit($actor, 'booking_rules.created', 'booking_rule_versions', $rules->id, ['version' => $rules->version, ...$data]);
        }, 3);
    }

    private function lockTenant(): Tenant
    {
        $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
        abort_unless($tenant->status === 'active', 404);

        return $tenant;
    }

    /** @param array<string, mixed> $changes */
    private function audit(User $actor, string $action, string $type, int $id, array $changes): void
    {
        $audit = new TenantAuditLog(['action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes' => $changes]);
        $audit->actor()->associate($actor);
        $audit->save();
    }
}
