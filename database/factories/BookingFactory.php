<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditGrant;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'credit_grant_id' => CreditGrant::factory(),
            'user_id' => fn (array $attributes): int => CreditGrant::query()->whereKey($attributes['credit_grant_id'])->sole()->user_id,
            'class_session_id' => ClassSession::factory()->state(['status' => 'published']),
            'booking_rule_version_id' => fn (array $attributes): int => ClassSession::query()->whereKey($attributes['class_session_id'])->sole()->booking_rule_version_id,
            'created_by_user_id' => fn (array $attributes): int => $attributes['user_id'],
            'status' => 'confirmed',
            'confirmed_at' => '2026-10-09 10:00:00',
            'rules_snapshot' => ['minimum_notice_minutes' => 120, 'booking_horizon_days' => 56, 'group_cancellation_minutes' => 720, 'private_cancellation_minutes' => 1440, 'studio_refund_validity_days' => 7, 'timezone' => 'Europe/Zagreb'],
            'credits_used' => 1,
            'operation_key' => (string) Str::uuid(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => 'cancelled',
            'cancellation_reason' => 'customer_timely',
            'cancelled_at' => '2026-10-10 10:00:00',
        ]);
    }
}
