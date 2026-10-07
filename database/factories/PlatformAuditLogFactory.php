<?php

namespace Database\Factories;

use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlatformAuditLog> */
class PlatformAuditLogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'actor_platform_admin_id' => PlatformAdmin::factory(),
            'target_tenant_id' => Tenant::factory(),
            'action' => 'tenant.created',
            'subject_type' => 'tenant',
            'subject_id' => fn (array $attributes): int => $attributes['target_tenant_id'],
            'changes' => null,
        ];
    }
}
