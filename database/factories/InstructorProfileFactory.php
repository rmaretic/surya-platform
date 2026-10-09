<?php

namespace Database\Factories;

use App\Models\InstructorProfile;
use App\Models\User;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InstructorProfile> */
class InstructorProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'user_id' => User::factory()->for(app(TenantContext::class)->requireTenant())->state(['role' => 'instructor']),
            'display_name' => fake()->name(),
        ];
    }
}
