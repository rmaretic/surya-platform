<?php

use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\User;
use App\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('M2 migrations preserve populated M1 identities domains profiles invitations and audit', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');
    expect(DB::connection()->getDatabaseName())->toBe('surya_testing');

    /** MySQL DDL commits implicitly; the next test must rebuild this dedicated test database. */
    DB::rollBack();
    RefreshDatabaseState::$migrated = false;
    $scheduling = require database_path('migrations/2026_10_09_130203_create_scheduling_foundation.php');
    $booking = require database_path('migrations/2026_10_09_130204_create_credit_and_booking_foundation.php');
    $booking->down();
    $scheduling->down();

    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    $user = User::factory()->for($tenant)->create([
        'role' => 'owner', 'two_factor_secret' => encrypt('existing-test-secret'),
        'two_factor_confirmed_at' => '2026-10-01 12:00:00',
    ]);
    TenantDomain::factory()->verified()->for($tenant)->create(['is_primary' => true]);
    TenantProfile::factory()->for($tenant)->create();
    $admin = PlatformAdmin::factory()->create();
    StaffInvitation::factory()->for($tenant)->create(['invited_by_platform_admin_id' => $admin->id]);
    TenantAuditLog::factory()->for($tenant)->create(['actor_user_id' => $user->id]);
    PlatformAuditLog::factory()->create(['actor_platform_admin_id' => $admin->id, 'target_tenant_id' => $tenant->id]);

    $tables = ['tenants', 'users', 'tenant_domains', 'tenant_profiles', 'platform_admins', 'staff_invitations', 'tenant_audit_logs', 'platform_audit_logs'];
    $before = [];
    foreach ($tables as $table) {
        $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
    }

    $scheduling->up();
    $booking->up();

    foreach ($tables as $table) {
        $after = DB::table($table)->orderBy('id')->get()->map(function (object $row) use ($table): array {
            $attributes = (array) $row;
            if ($table === 'tenants') {
                unset($attributes['timezone']);
            }

            return $attributes;
        })->all();
        expect($after)->toBe($before[$table]);
    }
    expect(DB::table('tenants')->where('id', $tenant->id)->value('timezone'))->toBe('Europe/Zagreb');
    expect(Schema::hasTable('bookings'))->toBeTrue();
    expect(Schema::hasTable('credit_entries'))->toBeTrue();
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 0);
});
