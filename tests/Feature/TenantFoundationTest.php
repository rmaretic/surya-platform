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
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalFoundationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Factories bypass model events here to exercise the MySQL constraints directly. */
test('the same normalized email belongs to separate users in different studios', function () {
    $first = User::factory()->createQuietly(['email' => ' Same@Example.test ']);

    $second = User::factory()->createQuietly(['email' => 'same@example.test']);

    app(TenantContext::class)->setTenant($second->tenant);
    expect($second->fresh()->normalized_email)->toBe('same@example.test');
    expect($second->tenant_id)->not->toBe($first->tenant_id);
    $this->assertDatabaseCount('users', 2);
});

test('duplicate normalized email is rejected in the same studio even through a raw write', function () {
    $user = User::factory()->createQuietly(['email' => 'same@example.test']);

    expect(fn () => DB::table('users')->insert([
        'tenant_id' => $user->tenant_id,
        'name' => 'Duplicate',
        'email' => ' SAME@EXAMPLE.TEST ',
        'password' => 'irrelevant',
    ]))->toThrow(QueryException::class);
});

test('changing email cannot bypass tenant uniqueness', function () {
    $first = User::factory()->createQuietly(['email' => 'same@example.test']);
    $second = User::factory()->for($first->tenant)->createQuietly();

    app(TenantContext::class)->setTenant($first->tenant);
    expect(fn () => $second->update(['email' => ' SAME@example.test ']))
        ->toThrow(QueryException::class);
});

test('a user cannot exist without a valid tenant', function (?int $tenantId) {
    expect(fn () => User::factory()->createQuietly(['tenant_id' => $tenantId]))
        ->toThrow(QueryException::class);
})->with([null, 999999]);

test('platform admins are separate identities and keep authentication secrets hidden', function () {
    $user = User::factory()->createQuietly(['email' => 'same@example.test']);

    $admin = PlatformAdmin::factory()->createQuietly([
        'email' => $user->email,
        'password' => 'admin-password',
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt('[]'),
    ]);

    expect(Hash::check('admin-password', $admin->password))->toBeTrue();
    expect($admin->toArray())->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token']);
    $this->assertDatabaseCount('platform_admins', 1);
    $this->assertDatabaseCount('users', 1);
});

test('platform admin normalized email is globally unique', function () {
    PlatformAdmin::factory()->createQuietly(['email' => 'admin@example.test']);

    expect(fn () => PlatformAdmin::factory()->createQuietly(['email' => ' ADMIN@EXAMPLE.TEST ']))
        ->toThrow(QueryException::class);
});

test('a hostname can only belong to one studio after normalization', function () {
    TenantDomain::factory()->createQuietly(['hostname' => 'lotus.yoga.test']);

    expect(fn () => TenantDomain::factory()->createQuietly(['hostname' => ' LOTUS.YOGA.TEST. ']))
        ->toThrow(QueryException::class);
});

test('new domains remain pending and do not acquire verification automatically', function () {
    $domain = TenantDomain::factory()->createQuietly(['hostname' => ' LOTUS.YOGA.TEST. ']);
    app(TenantContext::class)->setTenant($domain->tenant);
    $domain = $domain->fresh();

    expect($domain)->verification_status->toBe('pending')
        ->verified_at->toBeNull()
        ->normalized_hostname->toBe('lotus.yoga.test');
});

test('a studio can have multiple aliases but only one primary domain', function () {
    $tenant = Tenant::factory()->createQuietly();
    TenantDomain::factory()->for($tenant)->count(2)->createQuietly();
    TenantDomain::factory()->for($tenant)->createQuietly(['is_primary' => true]);

    expect(fn () => TenantDomain::factory()->for($tenant)->createQuietly(['is_primary' => true]))
        ->toThrow(QueryException::class);
});

test('each studio has at most one public profile', function () {
    $profile = TenantProfile::factory()->createQuietly();

    expect(fn () => TenantProfile::factory()->for($profile->tenant)->createQuietly())
        ->toThrow(QueryException::class);
});

test('an invitation rejects a user from another studio', function (string $column) {
    $foreignUser = User::factory()->createQuietly();
    $invitation = StaffInvitation::factory()->createQuietly();

    expect(fn () => DB::table('staff_invitations')->where('id', $invitation->id)->update([
        $column => $foreignUser->id,
        ...($column === 'invited_by_user_id' ? ['invited_by_platform_admin_id' => null] : []),
    ]))->toThrow(QueryException::class);
})->with(['invited_by_user_id', 'accepted_by_user_id']);

test('same-tenant invitation relationships resolve correctly and token hashes remain private', function () {
    $user = User::factory()->createQuietly();

    $invitation = StaffInvitation::factory()->for($user->tenant)->createQuietly([
        'invited_by_user_id' => $user->id,
        'invited_by_platform_admin_id' => null,
        'accepted_by_user_id' => $user->id,
    ]);

    app(TenantContext::class)->setTenant($user->tenant);
    expect($invitation->inviter->is($user))->toBeTrue();
    expect($invitation->recipient->is($user))->toBeTrue();
    expect($invitation->toArray())->not->toHaveKey('token_hash');
});

test('an invitation requires exactly one author identity', function (bool $both) {
    $user = User::factory()->createQuietly();

    expect(fn () => StaffInvitation::factory()->for($user->tenant)->createQuietly([
        'invited_by_user_id' => $both ? $user->id : null,
        'invited_by_platform_admin_id' => $both ? PlatformAdmin::factory()->createQuietly()->id : null,
    ]))->toThrow(QueryException::class);
})->with([false, true]);

test('tenant audit actors must belong to the audited studio', function () {
    $user = User::factory()->createQuietly();

    expect(fn () => TenantAuditLog::factory()->createQuietly(['actor_user_id' => $user->id]))
        ->toThrow(QueryException::class);
});

test('tenant and platform audit entries preserve their separate author relationships', function () {
    $user = User::factory()->createQuietly();
    $admin = PlatformAdmin::factory()->createQuietly();

    $tenantAudit = TenantAuditLog::factory()->for($user->tenant)->createQuietly(['actor_user_id' => $user->id]);
    $platformAudit = PlatformAuditLog::factory()->createQuietly(['actor_platform_admin_id' => $admin->id, 'target_tenant_id' => $user->tenant_id]);

    app(TenantContext::class)->setTenant($user->tenant);
    expect($tenantAudit->actor->is($user))->toBeTrue();
    expect($platformAudit->actor->is($admin))->toBeTrue();
    expect($platformAudit->targetTenant->is($user->tenant))->toBeTrue();
});

test('an authenticated session rejects a missing or different tenant', function (bool $missing) {
    $user = User::factory()->createQuietly();

    expect(fn () => DB::table('tenant_sessions')->insert([
        'id' => 'session',
        'tenant_id' => $missing ? null : Tenant::factory()->createQuietly()->id,
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => 1,
    ]))->toThrow(QueryException::class);
})->with([false, true]);

test('anonymous and same-tenant authenticated sessions can be stored', function () {
    $user = User::factory()->createQuietly();

    DB::table('tenant_sessions')->insert([
        ['id' => 'guest', 'tenant_id' => null, 'user_id' => null, 'payload' => '', 'last_activity' => 1],
        ['id' => 'user', 'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => 1],
    ]);

    $this->assertDatabaseCount('tenant_sessions', 2);
});

test('reset tokens for the same email are separate across studios and platform identities', function () {
    $tenants = Tenant::factory()->count(2)->createQuietly();

    foreach ($tenants as $tenant) {
        DB::table('tenant_password_reset_tokens')->insert([
            'tenant_id' => $tenant->id, 'email' => 'same@example.test', 'token' => Hash::make('token'),
        ]);
    }
    DB::table('platform_password_reset_tokens')->insert(['email' => 'same@example.test', 'token' => Hash::make('platform-token')]);

    $this->assertDatabaseCount('tenant_password_reset_tokens', 2);
    $this->assertDatabaseCount('platform_password_reset_tokens', 1);
});

test('normalized reset email remains unique inside a studio', function () {
    $tenant = Tenant::factory()->createQuietly();
    DB::table('tenant_password_reset_tokens')->insert(['tenant_id' => $tenant->id, 'email' => 'same@example.test', 'token' => 'hash']);

    expect(fn () => DB::table('tenant_password_reset_tokens')->insert([
        'tenant_id' => $tenant->id, 'email' => ' SAME@example.test ', 'token' => 'other-hash',
    ]))->toThrow(QueryException::class);
});

test('deleting a studio cannot silently delete its users', function () {
    $user = User::factory()->createQuietly();

    expect(fn () => $user->tenant->delete())->toThrow(QueryException::class);
    $this->assertModelExists($user);
});

test('untrusted mass assignment cannot choose a tenant role or domain verification', function () {
    $user = new User;
    $domain = new TenantDomain;

    $user->fill(['name' => 'User', 'tenant_id' => 99, 'role' => 'owner', 'is_active' => false]);
    $domain->fill(['hostname' => 'lotus.yoga.test', 'verification_status' => 'verified', 'verified_at' => now(), 'tenant_id' => 99]);

    expect($user->getAttributes())->toBe(['name' => 'User']);
    expect($domain->getAttributes())->toBe(['hostname' => 'lotus.yoga.test']);
});

test('default seeding creates no accounts or verified domains', function () {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('tenant_domains', 0);
});

test('explicit local seeding is repeatable and creates only the two demo studios', function () {
    $this->seed(LocalFoundationSeeder::class);
    $this->seed(LocalFoundationSeeder::class);

    $this->assertDatabaseCount('tenants', 2);
    $this->assertDatabaseCount('tenant_profiles', 2);
    $this->assertDatabaseCount('tenant_domains', 2);
    $this->assertDatabaseHas('tenant_domains', ['normalized_hostname' => 'lotus.yoga.test', 'verification_status' => 'verified', 'is_primary' => true]);
    $this->assertDatabaseHas('tenant_domains', ['normalized_hostname' => 'balance.yoga.test', 'verification_status' => 'verified', 'is_primary' => true]);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('platform_admins', 0);
});

test('local demo domains cannot be seeded in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect(fn () => $this->seed(LocalFoundationSeeder::class))->toThrow(LogicException::class);
        $this->assertDatabaseCount('tenants', 0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
