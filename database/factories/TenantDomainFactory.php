<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantDomain> */
class TenantDomainFactory extends Factory
{
    public function verified(): static
    {
        return $this->state(fn (): array => ['verification_status' => 'verified', 'verified_at' => now()]);
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'hostname' => fake()->unique()->domainWord().'.yoga.test',
            'verification_status' => 'pending',
            'is_active' => true,
            'is_primary' => false,
        ];
    }
}
