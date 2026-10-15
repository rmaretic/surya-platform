<?php

namespace App\Actions;

use App\Auth\AdministrativeAccess;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManageBooking
{
    /** @return array{can_cancel: bool, can_move: bool, returns_credit: bool, cancellation_deadline: string, timezone: string, message: string} */
    public function preview(User $actor, Booking $booking): array
    {
        return DB::transaction(function () use ($actor, $booking): array {
            [, $booking] = $this->authorize($actor, $booking);

            return $this->consequences($booking);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, Booking $booking, array $attributes, bool $move = false): Booking
    {
        $rules = [
            'operation_key' => ['required', 'uuid'],
            'tenant_id' => ['missing'], 'user_id' => ['missing'], 'credit_grant_id' => ['missing'],
            'status' => ['missing'], 'rules_snapshot' => ['missing'], 'credits_used' => ['missing'],
            'cancellation_reason' => ['missing'], 'cancelled_by_user_id' => ['missing'], 'cancelled_at' => ['missing'],
        ];
        $rules += $move ? [
            'class_session_id' => ['required_without:availability_rule_id', 'missing_with:availability_rule_id', 'integer', 'min:1'],
            'availability_rule_id' => ['required_without:class_session_id', 'missing_with:class_session_id', 'integer', 'min:1'],
            'starts_at' => ['required_with:availability_rule_id', 'missing_with:class_session_id', 'date_format:Y-m-d\TH:i:sP'],
        ] : ['class_session_id' => ['missing'], 'availability_rule_id' => ['missing'], 'starts_at' => ['missing']];
        $data = Validator::make($attributes, $rules)->validate();
        foreach (['class_session_id', 'availability_rule_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }
        if (isset($data['starts_at'])) {
            $data['starts_at'] = CarbonImmutable::parse($data['starts_at'])->utc()->toIso8601String();
        }

        return DB::transaction(function () use ($actor, $booking, $data, $move): Booking {
            [$actor, $booking] = $this->authorize($actor, $booking);
            $action = $move ? 'booking.moved' : 'booking.cancelled';
            $fingerprint = hash('sha256', json_encode([$action, $booking->id, $actor->id, $data], JSON_THROW_ON_ERROR));
            $previous = TenantAuditLog::query()->where('tenant_id', $booking->tenant_id)
                ->whereIn('action', ['booking.moved', 'booking.cancelled'])->where('changes->operation_key', $data['operation_key'])->first();
            if ($previous !== null) {
                $changes = $previous->getAttribute('changes');
                if (($changes['fingerprint'] ?? null) !== $fingerprint) {
                    throw ValidationException::withMessages(['operation_key' => 'Ključ operacije već je iskorišten za drukčiji zahtjev.']);
                }

                return Booking::query()->where('tenant_id', $booking->tenant_id)->whereKey($changes['result_booking_id'])->firstOrFail();
            }
            if (! $move && $booking->status === 'cancelled') {
                return $booking;
            }
            $consequences = $this->consequences($booking);
            if (! $consequences[$move ? 'can_move' : 'can_cancel']) {
                throw ValidationException::withMessages(['booking' => $move
                    ? 'Premještanje je dopušteno samo prije isteka besplatnog otkaznog roka.'
                    : 'Rezervaciju nije moguće otkazati nakon početka treninga.']);
            }
            $session = $booking->session()->lockForUpdate()->firstOrFail();
            $booking->forceFill([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->id,
                'cancellation_reason' => $move ? 'moved' : ($consequences['returns_credit'] ? 'customer_timely' : 'customer_late'),
            ])->save();
            if ($consequences['returns_credit']) {
                app(CreditLedger::class)->reverse($actor, $booking, $data['operation_key']);
            }
            $this->releasePrivateSession($session);
            $result = $move ? app(CreateBooking::class)->handle($actor, [...$data, 'user_id' => $booking->user_id], $booking) : $booking;
            (new TenantAuditLog)->forceFill([
                'actor_user_id' => $actor->id, 'action' => $action, 'subject_type' => 'bookings', 'subject_id' => $booking->id,
                'changes' => ['operation_key' => $data['operation_key'], 'fingerprint' => $fingerprint,
                    'result_booking_id' => $result->id, 'reason' => $booking->cancellation_reason,
                    'credit_returned' => $consequences['returns_credit']],
            ])->save();
            (new OutboxEvent)->forceFill([
                'booking_id' => $booking->id, 'class_session_id' => $session->id, 'recipient_user_id' => $booking->user_id,
                'event_type' => $action, 'aggregate_version' => $session->version,
                'deduplication_key' => "booking:{$booking->id}:cancelled",
                'payload' => ['reason' => $booking->cancellation_reason, 'replacement_booking_id' => $move ? $result->id : null,
                    'credit_returned' => $consequences['returns_credit']], 'available_at' => now(),
            ])->save();

            return $result;
        }, 3);
    }

    /** @return array{User, Booking} */
    private function authorize(User $actor, Booking $booking): array
    {
        $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
        abort_unless($tenant->status === 'active', 404);
        $actor = User::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($actor->id);
        $booking = Booking::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($booking->id);
        abort_unless(AdministrativeAccess::ready($actor) && (in_array($actor->role, ['owner', 'manager'], true)
            || ($actor->role === 'customer' && $actor->id === $booking->user_id)), 403);

        return [$actor, $booking];
    }

    /** @return array{can_cancel: bool, can_move: bool, returns_credit: bool, cancellation_deadline: string, timezone: string, message: string} */
    private function consequences(Booking $booking): array
    {
        $session = $booking->session()->firstOrFail();
        $private = $session->trainingType()->firstOrFail()->mode === 'private';
        $deadline = $session->starts_at->subMinutes($booking->rules_snapshot[$private ? 'private_cancellation_minutes' : 'group_cancellation_minutes']);
        $now = CarbonImmutable::now('UTC');
        $canCancel = $booking->status === 'confirmed' && $now->lt($session->starts_at);
        $timely = $canCancel && $now->lte($deadline);

        return [
            'can_cancel' => $canCancel, 'can_move' => $timely, 'returns_credit' => $timely,
            'cancellation_deadline' => $deadline->toIso8601String(), 'timezone' => $booking->rules_snapshot['timezone'],
            'message' => ! $canCancel ? 'Rezervacija je otkazana ili je trening već počeo.'
                : ($timely ? 'Otkaz vraća izvorni kredit s izvornim istekom. Nema povrata novca.'
                    : 'Kasni otkaz oslobađa mjesto. Kredit ostaje potrošen i premještanje nije dopušteno.'),
        ];
    }

    private function releasePrivateSession(ClassSession $session): void
    {
        if ($session->trainingType()->firstOrFail()->mode === 'private'
            && ! $session->bookings()->where('status', 'confirmed')->exists()) {
            $session->forceFill(['status' => 'cancelled', 'version' => $session->version + 1])->save();
        }
    }
}
