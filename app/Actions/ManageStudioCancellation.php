<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\CreditGrant;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageStudioCancellation
{
    /** @return array<string, mixed> */
    public function preview(User $actor, ClassSession $session): array
    {
        return DB::transaction(function () use ($actor, $session): array {
            $tenant = $this->lock($actor);
            $session = ClassSession::query()->where('tenant_id', $tenant->id)->findOrFail($session->id);
            $bookings = $session->bookings()->with(['user:id,name', 'grant', 'entries'])->orderBy('id')->get();

            return [
                'id' => $session->id, 'name' => $session->trainingType()->firstOrFail()->name,
                'local_start' => $session->starts_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                'status' => $session->status, 'can_cancel' => $this->canCancel($session),
                'preview_token' => $this->previewToken($session),
                'bookings' => $bookings->map(fn (Booking $booking): array => [
                    'id' => $booking->id, 'name' => $booking->user?->name, 'status' => $booking->status,
                    'affected' => $booking->status === 'confirmed',
                    'can_refund' => $actor->role === 'owner' && $booking->entries->contains('kind', 'debit') && ! $booking->entries->contains('kind', 'reversal'),
                    'refund_expires_at' => $this->refundExpiry($booking, $booking->grant)->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                ])->all(),
            ];
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function cancel(User $actor, ClassSession $session, array $attributes): void
    {
        $data = $this->validate($attributes, true);
        DB::transaction(function () use ($actor, $session, $data): void {
            $tenant = $this->lock($actor);
            $session = ClassSession::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($session->id);
            $fingerprint = $this->fingerprint('session', $actor, $session->id, $data);
            if ($this->replayed($data['operation_key'], $fingerprint)) {
                return;
            }
            if (! $this->canCancel($session)) {
                throw ValidationException::withMessages(['session' => 'Otkazati se može samo budući nacrt ili objavljeni termin.']);
            }
            if (! hash_equals($this->previewToken($session), $data['preview_token'])) {
                throw ValidationException::withMessages(['preview_token' => 'Termin ili rezervacije su izmijenjeni. Osvježite pregled prije otkaza.']);
            }
            $bookings = $session->bookings()->where('status', 'confirmed')->orderBy('id')->lockForUpdate()->get();
            $session->forceFill(['status' => 'cancelled', 'cancellation_reason' => $data['reason'], 'version' => $session->version + 1])->save();
            foreach ($bookings as $booking) {
                $this->cancelBooking($actor, $booking, 'studio_cancelled');
                $this->refund($actor, $booking, $data['reason']);
                $this->outbox($booking, $session, 'session.cancelled', $data['reason']);
            }
            $this->audit($actor, 'studio.session_cancelled', 'class_sessions', $session->id, $data, $fingerprint, $bookings->map(fn (Booking $booking): int => $booking->id)->values()->all());
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function refundException(User $actor, Booking $booking, array $attributes): void
    {
        $data = $this->validate($attributes, false);
        DB::transaction(function () use ($actor, $booking, $data): void {
            $tenant = $this->lock($actor, true);
            $booking = Booking::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($booking->id);
            $fingerprint = $this->fingerprint('exception', $actor, $booking->id, $data);
            if ($this->replayed($data['operation_key'], $fingerprint)) {
                return;
            }
            if ($booking->entries()->where('kind', 'reversal')->exists()) {
                throw ValidationException::withMessages(['booking' => 'Kredit ove rezervacije već je vraćen ili prenesen.']);
            }
            $session = $booking->session()->lockForUpdate()->firstOrFail();
            if ($booking->status === 'confirmed') {
                $this->cancelBooking($actor, $booking, 'owner_exception');
                if ($session->starts_at->gt(now()) && $session->trainingType()->firstOrFail()->mode === 'private') {
                    $session->forceFill(['status' => 'cancelled', 'cancellation_reason' => $data['reason'], 'version' => $session->version + 1])->save();
                }
            }
            $this->refund($actor, $booking, $data['reason']);
            $this->outbox($booking, $session, 'booking.refund_exception', $data['reason']);
            $this->audit($actor, 'studio.refund_exception', 'bookings', $booking->id, $data, $fingerprint, [$booking->id]);
        }, 3);
    }

    private function lock(User $actor, bool $ownerOnly = false): Tenant
    {
        $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
        abort_unless($tenant->status === 'active', 404);
        $actor = User::query()->where('tenant_id', $tenant->id)->findOrFail($actor->id);
        Gate::forUser($actor)->authorize($ownerOnly ? 'studio-credits' : 'studio-catalog');

        return $tenant;
    }

    private function canCancel(ClassSession $session): bool
    {
        return in_array($session->status, ['draft', 'published'], true) && $session->starts_at->gt(now());
    }

    private function previewToken(ClassSession $session): string
    {
        return hash('sha256', json_encode([$session->id, $session->version, $session->status,
            $session->bookings()->where('status', 'confirmed')->orderBy('id')->pluck('id')->all()], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function validate(array $attributes, bool $session): array
    {
        return Validator::make($attributes, [
            'operation_key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:2000'],
            'preview_token' => $session ? ['required', 'string', 'size:64'] : ['missing'],
            'tenant_id' => ['missing'], 'user_id' => ['missing'], 'credit_grant_id' => ['missing'],
            'expires_at' => ['missing'], 'credits' => ['missing'], 'status' => ['missing'],
        ])->validate();
    }

    private function cancelBooking(User $actor, Booking $booking, string $reason): void
    {
        $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor->id, 'cancellation_reason' => $reason])->save();
    }

    private function refundExpiry(Booking $booking, CreditGrant $grant): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfSecond()->setTimezone($booking->rules_snapshot['timezone'])
            ->addDays(max(7, (int) $booking->rules_snapshot['studio_refund_validity_days']))->utc()->max($grant->expires_at);
    }

    /** Moves only the returned unit when its original grant expires too soon. */
    private function refund(User $actor, Booking $booking, string $reason): void
    {
        $debit = $booking->entries()->where('kind', 'debit')->first();
        if ($debit === null) {
            throw ValidationException::withMessages(['booking' => 'Rezervacija nema izvorni debit; povrat nije izvršen.']);
        }
        $grant = $booking->grant()->lockForUpdate()->firstOrFail();
        $expires = $this->refundExpiry($booking, $grant);
        app(CreditLedger::class)->reverse($actor, $booking, (string) Str::uuid());
        if ($expires->equalTo($grant->expires_at)) {
            return;
        }
        $key = 'compensation:'.$debit->id;
        (new CreditEntry)->forceFill([
            'credit_grant_id' => $grant->id, 'created_by_user_id' => $actor->id,
            'kind' => 'adjustment', 'amount' => -1, 'reason' => $reason,
            'operation_key' => $key.':transfer', 'occurred_at' => now(),
        ])->save();
        $compensation = (new CreditGrant)->forceFill([
            'user_id' => $booking->user_id, 'package_product_id' => $grant->package_product_id,
            'created_by_user_id' => $actor->id, 'source' => 'complimentary', 'reason' => $reason,
            'compensates_credit_entry_id' => $debit->id, 'operation_key' => $key,
            'granted_at' => now(), 'valid_from' => now(), 'expires_at' => $expires, 'credits' => 1,
            'product_snapshot' => [...$grant->product_snapshot, 'credits' => 1, 'price_cents' => 0,
                'validity_days' => max(7, (int) $booking->rules_snapshot['studio_refund_validity_days'])],
        ]);
        $compensation->save();
        $compensation->trainingTypes()->sync($grant->trainingTypes()->pluck('training_types.id')->all());
        (new CreditEntry)->forceFill([
            'credit_grant_id' => $compensation->id, 'created_by_user_id' => $actor->id,
            'kind' => 'grant', 'amount' => 1, 'operation_key' => $key.':grant', 'occurred_at' => now(),
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(string $operation, User $actor, int $id, array $data): string
    {
        return hash('sha256', json_encode([$operation, $actor->id, $id, $data], JSON_THROW_ON_ERROR));
    }

    private function replayed(string $key, string $fingerprint): bool
    {
        $previous = TenantAuditLog::query()->where('tenant_id', app(TenantContext::class)->requireTenant()->id)
            ->whereIn('action', ['studio.session_cancelled', 'studio.refund_exception'])->where('changes->operation_key', $key)->first();
        if ($previous !== null && $previous->getAttribute('changes')['fingerprint'] !== $fingerprint) {
            throw ValidationException::withMessages(['operation_key' => 'Ključ operacije već je iskorišten za drukčiji zahtjev.']);
        }

        return $previous !== null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $bookings
     */
    private function audit(User $actor, string $action, string $type, int $id, array $data, string $fingerprint, array $bookings): void
    {
        (new TenantAuditLog)->forceFill(['actor_user_id' => $actor->id, 'action' => $action,
            'subject_type' => $type, 'subject_id' => $id,
            'changes' => [...$data, 'fingerprint' => $fingerprint, 'booking_ids' => $bookings],
        ])->save();
    }

    private function outbox(Booking $booking, ClassSession $session, string $event, string $reason): void
    {
        (new OutboxEvent)->forceFill([
            'booking_id' => $booking->id, 'class_session_id' => $session->id, 'recipient_user_id' => $booking->user_id,
            'event_type' => $event, 'aggregate_version' => $session->version,
            'deduplication_key' => $event.':'.$booking->id,
            'payload' => ['reason' => $reason, 'credit_returned' => true], 'available_at' => now(),
        ])->save();
    }
}
