<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\CreditGrant;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CreditEntry> */
class CreditEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'credit_grant_id' => CreditGrant::factory(),
            'created_by_user_id' => fn (array $attributes): int => CreditGrant::query()->whereKey($attributes['credit_grant_id'])->sole()->created_by_user_id,
            'kind' => 'grant',
            'amount' => 5,
            'occurred_at' => '2026-10-09 10:00:00',
            'operation_key' => (string) Str::uuid(),
        ];
    }

    public function debit(Booking $booking): static
    {
        return $this->state(fn (): array => [
            'credit_grant_id' => $booking->credit_grant_id,
            'booking_id' => $booking->id,
            'kind' => 'debit',
            'amount' => -1,
        ]);
    }

    public function reversal(CreditEntry $debit): static
    {
        return $this->state(fn (): array => [
            'credit_grant_id' => $debit->credit_grant_id,
            'booking_id' => $debit->booking_id,
            'reverses_entry_id' => $debit->id,
            'kind' => 'reversal',
            'amount' => 1,
        ]);
    }
}
