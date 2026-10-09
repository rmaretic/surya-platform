<?php

namespace Database\Factories;

use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AvailabilityException> */
class AvailabilityExceptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'availability_rule_id' => AvailabilityRule::factory(),
            'local_date' => '2026-10-12',
            'is_unavailable' => true,
        ];
    }
}
