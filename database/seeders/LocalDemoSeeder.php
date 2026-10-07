<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use App\Models\Scopes\TenantScope;
use App\Models\TenantDomain;
use App\Models\User;
use App\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('LocalDemoSeeder may only run locally or in tests.');
        }

        try {
            DB::transaction(function (): void {
                $this->call(LocalFoundationSeeder::class);

                foreach (['lotus' => 'Lotus', 'balance' => 'Balance'] as $design => $name) {
                    $domain = TenantDomain::withoutGlobalScope(TenantScope::class)
                        ->where('normalized_hostname', $design.'.yoga.test')->firstOrFail();
                    $tenant = $domain->tenant;

                    if ($tenant->design_key !== $design || $tenant->status !== 'active'
                        || $domain->verification_status !== 'verified' || ! $domain->is_active || ! $domain->is_primary) {
                        throw new LogicException('Existing demo studio configuration conflicts with the demo seed. No changes were saved.');
                    }

                    app(TenantContext::class)->setTenant($tenant);
                    $profile = $tenant->profile()->firstOrCreate([], ['name' => $name]);
                    $defaults = [
                        'short_description' => $design === 'lotus'
                            ? 'Demo studio Lotus — miran prostor za dah, nježan pokret i trenutak za sebe.'
                            : 'Demo studio Balance — energija pokreta, snaga i ravnoteža u vlastitom ritmu.',
                        'email' => $design.'@example.test',
                        'address' => $design === 'lotus' ? 'Demo adresa — vrt Lotusa' : 'Demo adresa — trg Balancea',
                    ];

                    foreach ($defaults as $field => $value) {
                        if ($profile->getAttribute($field) === null) {
                            $profile->setAttribute($field, $value);
                        }
                    }
                    if ($profile->isDirty()) {
                        $profile->save();
                    }

                    foreach (['owner', 'manager', 'instructor', 'customer'] as $role) {
                        $email = $role === 'customer' ? 'customer@example.test' : $role.'.'.$design.'@example.test';
                        $user = User::query()->where('normalized_email', $email)->firstOrCreate([], [
                            'email' => $email,
                            'name' => $name.' demo '.$role,
                            'password' => $name.'-Demo-2026!',
                            'role' => $role,
                            'is_active' => true,
                            'email_verified_at' => now(),
                        ]);

                        if ($user->role !== $role) {
                            throw new LogicException('An existing demo email has a different role. No changes were saved.');
                        }
                    }
                    app(TenantContext::class)->clear();
                }

                PlatformAdmin::query()->where('normalized_email', 'platform@example.test')->firstOrCreate([], [
                    'email' => 'platform@example.test',
                    'name' => 'Demo platform administrator',
                    'password' => 'Platform-Demo-2026!',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            });
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
