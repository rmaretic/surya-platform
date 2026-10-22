<?php

namespace App\Actions;

use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManageAttendance
{
    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, Booking $booking, array $attributes): void
    {
        $data = Validator::make($attributes, [
            'operation_key' => ['required', 'uuid'],
            'status' => ['required', 'in:present,no_show,pending'],
            'expected_status' => ['required', 'in:present,no_show,pending'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        DB::transaction(function () use ($actor, $booking, $data): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
            abort_unless($tenant->status === 'active', 404);
            $actor = User::query()->where('tenant_id', $tenant->id)->findOrFail($actor->id);
            $booking = Booking::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($booking->id);
            $session = $booking->session()->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('studio-attendance', $session);
            $fingerprint = hash('sha256', json_encode([$actor->id, $booking->id, $data], JSON_THROW_ON_ERROR));
            $previousOperation = TenantAuditLog::query()->where('tenant_id', $tenant->id)
                ->where('action', 'studio.attendance_recorded')->where('changes->operation_key', $data['operation_key'])->first();
            if ($previousOperation !== null) {
                if (($previousOperation->changes['fingerprint'] ?? null) !== $fingerprint) {
                    throw ValidationException::withMessages(['operation_key' => 'Ključ operacije već je iskorišten za drukčiji zahtjev.']);
                }

                return;
            }
            if ($booking->status !== 'confirmed' || ! in_array($session->status, ['published', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => 'Dolazak se može evidentirati samo za potvrđenu rezervaciju neotkazanog termina.']);
            }
            if ($data['status'] === 'no_show' && $session->ends_at->gt(now())) {
                throw ValidationException::withMessages(['status' => 'Nedolazak potvrdite tek nakon završetka termina.']);
            }
            $record = $booking->attendance()->lockForUpdate()->first();
            $previous = $record->status ?? 'pending';
            if ($previous === $data['status']) {
                $this->audit($actor, $booking, $previous, $data['status'], $data['reason'] ?? null, $data['operation_key'], $fingerprint);

                return;
            }
            if ($previous !== $data['expected_status']) {
                throw ValidationException::withMessages(['expected_status' => 'Evidencija je izmijenjena. Osvježite pregled prije ponovnog upisa.']);
            }
            $reason = trim($data['reason'] ?? '');
            if ($previous !== 'pending' && $reason === '') {
                throw ValidationException::withMessages(['reason' => 'Za ispravak evidencije unesite razlog.']);
            }
            $record ??= new AttendanceRecord;
            $record->forceFill([
                'tenant_id' => $tenant->id, 'booking_id' => $booking->id,
                'status' => $data['status'],
                'recorded_by_user_id' => $data['status'] === 'pending' ? null : $actor->id,
                'recorded_at' => $data['status'] === 'pending' ? null : now(),
                'correction_reason' => $previous === 'pending' ? null : $reason,
            ])->save();
            $this->audit($actor, $booking, $previous, $data['status'], $reason, $data['operation_key'], $fingerprint);
        }, 3);
    }

    private function audit(User $actor, Booking $booking, string $previous, string $status, ?string $reason, string $operationKey, string $fingerprint): void
    {
        (new TenantAuditLog)->forceFill([
            'actor_user_id' => $actor->id, 'action' => 'studio.attendance_recorded',
            'subject_type' => 'bookings', 'subject_id' => $booking->id,
            'changes' => ['from' => $previous, 'to' => $status, 'reason' => trim($reason ?? '') ?: null,
                'operation_key' => $operationKey, 'fingerprint' => $fingerprint],
        ])->save();
    }
}
