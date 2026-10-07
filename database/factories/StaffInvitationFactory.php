<?php

namespace Database\Factories;

use App\Models\PlatformAdmin;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StaffInvitation> */
class StaffInvitationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => fake()->safeEmail(),
            'role' => 'owner',
            'token_hash' => hash('sha256', Str::random(64)),
            'invited_by_platform_admin_id' => PlatformAdmin::factory(),
            'expires_at' => now()->addDay(),
        ];
    }
}
