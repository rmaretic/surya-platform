<?php

namespace Database\Factories;

use App\Models\TrainingType;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingType> */
class TrainingTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'name' => fake()->words(2, true),
            'mode' => 'group',
            'duration_minutes' => 60,
            'capacity' => 10,
        ];
    }
}
