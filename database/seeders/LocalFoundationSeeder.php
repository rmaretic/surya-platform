<?php

namespace Database\Seeders;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class LocalFoundationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('LocalFoundationSeeder may only run locally or in tests.');
        }

        DB::transaction(function (): void {
            foreach (['lotus' => 'Lotus', 'balance' => 'Balance'] as $design => $name) {
                $hostname = $design.'.yoga.test';
                $domain = TenantDomain::withoutGlobalScope(TenantScope::class)->where('normalized_hostname', $hostname)->first();

                if ($domain !== null) {
                    continue;
                }

                $tenant = Tenant::query()->create([
                    'name' => $name,
                    'status' => 'active',
                    'design_key' => $design,
                ]);

                app(TenantContext::class)->setTenant($tenant);
                try {
                    $tenant->profile()->create(['name' => $name]);
                    $domain = new TenantDomain;
                    $domain->forceFill([
                        'tenant_id' => $tenant->id,
                        'hostname' => $hostname,
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                        'is_active' => true,
                        'is_primary' => true,
                    ])->save();
                } finally {
                    app(TenantContext::class)->clear();
                }
            }
        });
    }
}
