<?php

namespace Database\Factories;

use App\Models\BookingRuleVersion;
use App\Models\User;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingRuleVersion> */
class BookingRuleVersionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'version' => fake()->unique()->numberBetween(1, 1000000),
            'created_by_user_id' => User::factory()->for(app(TenantContext::class)->requireTenant())->state(['role' => 'owner']),
            'minimum_notice_minutes' => 120,
            'booking_horizon_days' => 56,
            'group_cancellation_minutes' => 720,
            'private_cancellation_minutes' => 1440,
            'studio_refund_validity_days' => 7,
        ];
    }
}
