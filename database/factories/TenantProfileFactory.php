<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantProfile> */
class TenantProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'short_description' => null,
            'email' => fake()->safeEmail(),
            'phone' => null,
            'address' => null,
        ];
    }
}
