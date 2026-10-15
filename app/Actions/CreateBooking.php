<?php

namespace App\Actions;

use App\Auth\AdministrativeAccess;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditGrant;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    /**
     * The optional replacement is internal to ManageBooking's atomic transfer.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, array $attributes, ?Booking $replaces = null): Booking
    {
        if ($replaces !== null && DB::transactionLevel() < 1) {
            throw new \LogicException('A transfer requires the enclosing cancellation transaction and tenant lock.');
        }
        $data = Validator::make($attributes, [
            'operation_key' => ['required', 'uuid'],
            'user_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'class_session_id' => ['required_without:availability_rule_id', 'missing_with:availability_rule_id', 'integer', 'min:1'],
            'availability_rule_id' => ['required_without:class_session_id', 'missing_with:class_session_id', 'integer', 'min:1'],
            'starts_at' => ['required_with:availability_rule_id', 'missing_with:class_session_id', 'date_format:Y-m-d\TH:i:sP'],
            'tenant_id' => ['missing'], 'credit_grant_id' => ['missing'],
            'status' => ['missing'], 'credits_used' => ['missing'], 'rules_snapshot' => ['missing'],
            'created_by_user_id' => ['missing'], 'ends_at' => ['missing'],
        ])->validate();

        return DB::transaction(function () use ($actor, $data, $replaces): Booking {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
            abort_unless($tenant->status === 'active', 404);
            $userId = (int) ($data['user_id'] ?? $actor->id);
            $users = User::query()->where('tenant_id', $tenant->id)->whereIn('id', [$actor->id, $userId])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            $customer = $users->get($userId);
            abort_unless($actor && $customer, 404);
            abort_unless(AdministrativeAccess::ready($actor) && (in_array($actor->role, ['owner', 'manager'], true)
                || ($actor->role === 'customer' && $actor->id === $customer->id)), 403);
            if (! $customer->is_active || $customer->role !== 'customer') {
                throw ValidationException::withMessages(['user_id' => 'Odaberite aktivnog polaznika ovog studija.']);
            }
            if ($replaces !== null) {
                $replaces = Booking::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($replaces->id);
                abort_unless($replaces->user_id === $customer->id && $replaces->status === 'cancelled'
                    && $replaces->cancellation_reason === 'moved' && $replaces->entries()->where('kind', 'reversal')->exists(), 422);
            }

            $start = isset($data['starts_at']) ? CarbonImmutable::parse($data['starts_at'])->utc() : null;
            $fingerprint = hash('sha256', json_encode([
                'actor' => $actor->id, 'customer' => $customer->id,
                'session' => isset($data['class_session_id']) ? (int) $data['class_session_id'] : null,
                'availability' => isset($data['availability_rule_id']) ? (int) $data['availability_rule_id'] : null,
                'start' => $start?->toIso8601String(),
                ...($replaces === null ? [] : ['replaces' => $replaces->id]),
            ], JSON_THROW_ON_ERROR));
            $existing = Booking::query()->where('tenant_id', $tenant->id)->where('operation_key', $data['operation_key'])->lockForUpdate()->first();
            if ($existing) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['operation_key' => 'Ovaj ključ već pripada drukčijoj rezervaciji.']);
                }

                return $existing;
            }

            $session = $start === null
                ? ClassSession::query()->where('tenant_id', $tenant->id)->whereKey($data['class_session_id'])->lockForUpdate()->firstOrFail()
                : $this->privateSession($tenant, (int) $data['availability_rule_id'], $start);
            $this->checkSession($tenant, $session, $start !== null);
            if (Booking::query()->where('tenant_id', $tenant->id)->where('user_id', $customer->id)->where('status', 'confirmed')
                ->whereHas('session', fn ($query) => $query->where('starts_at', '<', $session->ends_at)->where('ends_at', '>', $session->starts_at))->exists()) {
                throw ValidationException::withMessages(['class_session_id' => 'Već imate rezervaciju koja se preklapa s ovim terminom.']);
            }
            if ($replaces !== null) {
                $original = $replaces->session()->firstOrFail();
                if ($session->training_type_id !== $original->training_type_id || $replaces->credits_used !== 1
                    || $session->id === $original->id
                    || $session->starts_at->diffInSeconds($session->ends_at) !== $original->starts_at->diffInSeconds($original->ends_at)
                    || ($start !== null && $session->starts_at->eq($original->starts_at))) {
                    throw ValidationException::withMessages(['class_session_id' => 'Premještanje zahtijeva drugi termin iste vrste, trajanja i troška kredita.']);
                }
            }
            $grant = $this->grant($tenant, $customer, $session, $replaces?->credit_grant_id);
            $policy = $session->ruleVersion()->firstOrFail();
            $booking = new Booking;
            $booking->forceFill([
                'user_id' => $customer->id, 'created_by_user_id' => $actor->id,
                'class_session_id' => $session->id, 'credit_grant_id' => $grant->id,
                'booking_rule_version_id' => $replaces->booking_rule_version_id ?? $policy->id,
                'status' => 'confirmed', 'confirmed_at' => now(), 'credits_used' => 1,
                'operation_key' => $data['operation_key'], 'request_fingerprint' => $fingerprint,
                'rules_snapshot' => $replaces->rules_snapshot ?? [
                    ...$policy->only(['minimum_notice_minutes', 'booking_horizon_days', 'group_cancellation_minutes', 'private_cancellation_minutes', 'studio_refund_validity_days']),
                    'timezone' => $tenant->timezone,
                ],
            ])->save();
            app(CreditLedger::class)->debit($actor, $booking, $data['operation_key']);
            (new OutboxEvent)->forceFill([
                'booking_id' => $booking->id, 'class_session_id' => $session->id, 'recipient_user_id' => $customer->id,
                'event_type' => 'booking.confirmed', 'aggregate_version' => $session->version,
                'deduplication_key' => "booking:{$booking->id}:confirmed",
                'payload' => ['starts_at' => $session->starts_at->toIso8601String(), 'ends_at' => $session->ends_at->toIso8601String()],
                'available_at' => now(),
            ])->save();
            (new TenantAuditLog)->forceFill([
                'actor_user_id' => $actor->id, 'action' => 'booking.confirmed',
                'subject_type' => 'bookings', 'subject_id' => $booking->id,
                'changes' => ['class_session_id' => $session->id, 'user_id' => $customer->id, 'credit_grant_id' => $grant->id],
            ])->save();

            return $booking;
        }, 3);
    }

    private function privateSession(Tenant $tenant, int $ruleId, CarbonImmutable $start): ClassSession
    {
        $rule = AvailabilityRule::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($ruleId);
        $slots = app(CalculatePrivateSlots::class)->handle($start->setTimezone($rule->timezone)->format('Y-m-d'), $rule->id);
        if (! collect($slots['slots'])->contains(fn (array $slot): bool => $slot['starts_at'] === $start->toIso8601String())) {
            throw ValidationException::withMessages(['starts_at' => 'Odabrani privatni termin više nije dostupan. Osvježite raspored.']);
        }
        $session = new ClassSession;
        $session->forceFill([
            ...$rule->only(['training_type_id', 'instructor_profile_id', 'room_id', 'booking_rule_version_id', 'buffer_before_minutes', 'buffer_after_minutes']),
            'starts_at' => $start, 'ends_at' => $start->addMinutes($rule->duration_minutes),
            'capacity' => 1, 'status' => 'published', 'version' => 1,
        ])->save();

        return $session;
    }

    private function checkSession(Tenant $tenant, ClassSession $session, bool $private): void
    {
        $session->load(['trainingType', 'instructor.user', 'room', 'ruleVersion']);
        $type = $session->trainingType;
        $instructor = $session->instructor;
        $room = $session->room;
        $policy = $session->ruleVersion;
        if ($session->status !== 'published' || $type === null || $type->archived_at !== null
            || $type->mode !== ($private ? 'private' : 'group') || $instructor === null || $instructor->archived_at !== null
            || ! $instructor->user?->is_active || ! in_array($instructor->user->role, ['owner', 'manager', 'instructor'], true)
            || $room === null || $room->archived_at !== null || $room->capacity < $session->capacity || $policy === null) {
            throw ValidationException::withMessages(['class_session_id' => 'Termin nije dostupan za rezervaciju.']);
        }
        $now = CarbonImmutable::now('UTC');
        if ($session->starts_at->lte($now) || $session->starts_at->lt($now->addMinutes($policy->minimum_notice_minutes))
            || $session->starts_at->gt($now->setTimezone($tenant->timezone)->addDays($policy->booking_horizon_days)->utc())) {
            throw ValidationException::withMessages(['class_session_id' => 'Termin je izvan dopuštenog roka za rezervaciju.']);
        }
        if ($session->bookings()->where('tenant_id', $tenant->id)->where('status', 'confirmed')->count() >= $session->capacity) {
            throw ValidationException::withMessages(['class_session_id' => 'Termin je popunjen.']);
        }
        $conflict = ClassSession::query()->where('tenant_id', $tenant->id)->where('id', '!=', $session->id)->whereIn('status', ['draft', 'published'])
            ->where(fn ($query) => $query->where('instructor_profile_id', $session->instructor_profile_id)->orWhere('room_id', $session->room_id))
            ->whereRaw('DATE_SUB(starts_at, INTERVAL buffer_before_minutes MINUTE) < ?', [$session->ends_at->addMinutes($session->buffer_after_minutes)->format('Y-m-d H:i:s')])
            ->whereRaw('DATE_ADD(ends_at, INTERVAL buffer_after_minutes MINUTE) > ?', [$session->starts_at->subMinutes($session->buffer_before_minutes)->format('Y-m-d H:i:s')])->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['class_session_id' => 'Instruktor ili prostor već je zauzet (uključujući buffer).']);
        }
    }

    private function grant(Tenant $tenant, User $customer, ClassSession $session, ?int $grantId = null): CreditGrant
    {
        $grants = CreditGrant::query()->where('tenant_id', $tenant->id)->where('user_id', $customer->id)
            ->when($grantId !== null, fn ($query) => $query->whereKey($grantId))
            ->where('valid_from', '<=', $session->starts_at)->where('expires_at', '>', $session->starts_at)
            ->whereHas('trainingTypes', fn ($query) => $query->whereKey($session->training_type_id))
            ->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($grants as $grant) {
            if ((int) $grant->entries()->sum('amount') > 0) {
                return $grant;
            }
        }

        throw ValidationException::withMessages(['credit_grant_id' => 'Nemate raspoloživ kredit koji vrijedi za ovaj termin. Kontaktirajte studio.']);
    }
}
