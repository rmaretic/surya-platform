<?php

namespace App\Actions;

use App\Auth\AdministrativeAccess;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\CreditGrant;
use App\Models\Tenant;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreditLedger
{
    /**
     * Internal booking primitive. The caller must authorize the business operation
     * and create/confirm the booking in the same transaction, tenant lock first.
     */
    public function debit(User $actor, Booking $booking, string $operationKey): CreditEntry
    {
        return $this->movement($actor, $booking, $operationKey, false);
    }

    /** Internal cancellation primitive; cancellation policy belongs to the caller. */
    public function reverse(User $actor, Booking $booking, string $operationKey): CreditEntry
    {
        return $this->movement($actor, $booking, $operationKey, true);
    }

    /** @return array{balance: int, available: int, reserved: int, expired: int, pending: int} */
    public function balance(CreditGrant $grant, ?CarbonImmutable $at = null): array
    {
        $balances = $this->balances(new Collection([$grant]), $at);
        abort_unless(isset($balances[$grant->id]), 404);

        return $balances[$grant->id];
    }

    /**
     * @param  Collection<int, CreditGrant>  $grants
     * @return array<int, array{balance: int, available: int, reserved: int, expired: int, pending: int}>
     */
    public function balances(Collection $grants, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now('UTC');
        $records = CreditGrant::query()->where('tenant_id', app(TenantContext::class)->requireTenant()->id)
            ->whereKey($grants->modelKeys())->withSum('entries as ledger_balance', 'amount')
            ->withSum(['bookings as reserved_credits' => fn ($query) => $query->where('status', 'confirmed')
                ->whereHas('session', fn ($query) => $query->where('ends_at', '>', $at))
                ->whereHas('entries', fn ($query) => $query->where('kind', 'debit'))], 'credits_used')->get();
        $balances = [];
        foreach ($records as $record) {
            $balance = (int) $record->getAttribute('ledger_balance');
            $balances[$record->id] = [
                'balance' => $balance,
                'available' => $at->gte($record->valid_from) && $at->lt($record->expires_at) ? $balance : 0,
                'reserved' => (int) $record->getAttribute('reserved_credits'),
                'expired' => $at->gte($record->expires_at) ? $balance : 0,
                'pending' => $at->lt($record->valid_from) ? $balance : 0,
            ];
        }

        return $balances;
    }

    private function movement(User $actor, Booking $booking, string $operationKey, bool $reverse): CreditEntry
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Ledger booking movements require the enclosing booking transaction and tenant lock.');
        }
        Validator::make(['operation_key' => $operationKey], ['operation_key' => ['required', 'uuid']])->validate();
        $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
        abort_unless($tenant->status === 'active', 404);
        $actor = User::query()->where('tenant_id', $tenant->id)->findOrFail($actor->id);
        $booking = Booking::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($booking->id);
        abort_unless(AdministrativeAccess::ready($actor) && (in_array($actor->role, ['owner', 'manager'], true)
            || ($actor->role === 'customer' && $actor->id === $booking->user_id)), 403);
        $kind = $reverse ? 'reversal' : 'debit';
        $key = $kind.':'.$operationKey;
        $existing = CreditEntry::query()->where('tenant_id', $tenant->id)->where('operation_key', $key)->first();
        if ($existing) {
            if ($existing->booking_id !== $booking->id) {
                throw ValidationException::withMessages(['operation_key' => 'Ovaj ključ već pripada drugoj rezervaciji.']);
            }

            return $existing;
        }
        $grant = CreditGrant::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($booking->credit_grant_id);
        $debit = $booking->entries()->where('kind', 'debit')->first();
        if ($reverse) {
            if ($booking->status !== 'cancelled' || ! $debit) {
                throw ValidationException::withMessages(['booking' => 'Povrat zahtijeva otkazanu rezervaciju s izvornim debitom.']);
            }
            $previous = CreditEntry::query()->where('tenant_id', $tenant->id)->where('reverses_entry_id', $debit->id)->first();
            if ($previous) {
                return $previous;
            }
        } else {
            if ($debit) {
                return $debit;
            }
            $session = $booking->session()->firstOrFail();
            if ($booking->status !== 'confirmed' || $booking->user_id !== $grant->user_id
                || $session->starts_at->lt($grant->valid_from) || $session->starts_at->gte($grant->expires_at)
                || ! $grant->trainingTypes()->whereKey($session->training_type_id)->exists()) {
                throw ValidationException::withMessages(['credit_grant_id' => 'Pravo ne pokriva ovu potvrđenu rezervaciju.']);
            }
            if ((int) $grant->entries()->sum('amount') < $booking->credits_used) {
                throw ValidationException::withMessages(['credit_grant_id' => 'Nema dovoljno raspoloživih kredita.']);
            }
        }
        $entry = new CreditEntry;
        $entry->forceFill([
            'credit_grant_id' => $grant->id, 'booking_id' => $booking->id,
            'created_by_user_id' => $actor->id, 'kind' => $kind, 'amount' => $reverse ? 1 : -1,
            'reverses_entry_id' => $reverse ? $debit->id : null,
            'operation_key' => $key, 'occurred_at' => now(),
        ])->save();

        return $entry;
    }
}
