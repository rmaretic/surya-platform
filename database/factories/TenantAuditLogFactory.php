<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantAuditLog> */
class TenantAuditLogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'action' => 'profile.updated',
            'subject_type' => 'tenant_profile',
            'subject_id' => 1,
            'changes' => ['name' => ['before' => 'Old name', 'after' => 'New name']],
        ];
    }
}
