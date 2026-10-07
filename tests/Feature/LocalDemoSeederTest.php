<?php

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\TenantContext;
use Database\Seeders\LocalDemoSeeder;
use Database\Seeders\LocalFoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

test('demo seed creates distinct studios and all roles without sending mail', function () {
    Notification::fake();

    $this->seed(LocalDemoSeeder::class);
    $this->seed(LocalDemoSeeder::class);

    $this->assertDatabaseCount('tenants', 2);
    $this->assertDatabaseCount('tenant_domains', 2);
    $this->assertDatabaseCount('tenant_profiles', 2);
    $this->assertDatabaseCount('users', 8);
    $this->assertDatabaseCount('platform_admins', 1);
    foreach (['lotus' => 'Lotus', 'balance' => 'Balance'] as $design => $name) {
        $tenant = Tenant::query()->where('design_key', $design)->firstOrFail();
        app(TenantContext::class)->setTenant($tenant);
        expect(User::query()->pluck('role')->sort()->values()->all())->toBe(['customer', 'instructor', 'manager', 'owner']);
        foreach (User::query()->get() as $user) {
            expect(Hash::check($name.'-Demo-2026!', $user->password))->toBeTrue();
            expect($user->email_verified_at)->not->toBeNull();
            expect($user->two_factor_secret)->toBeNull();
        }
        $this->assertDatabaseHas('tenant_domains', ['tenant_id' => $tenant->id, 'normalized_hostname' => $design.'.yoga.test', 'verification_status' => 'verified']);
        $this->get('http://'.$design.'.yoga.test')->assertInertia(fn (Assert $page) => $page
            ->component('sites/'.$design.'/Home')->where('profile.name', $name)
            ->where('profile.email', $design.'@example.test'));
    }
    expect(Hash::check('Platform-Demo-2026!', PlatformAdmin::query()->sole()->password))->toBeTrue();
    expect(app(TenantContext::class)->tenant())->toBeNull();
    Notification::assertNothingSent();
});

test('repeated seed preserves edited profiles credentials enrolment and deactivation', function () {
    $this->seed(LocalDemoSeeder::class);
    $tenant = Tenant::query()->where('design_key', 'lotus')->firstOrFail();
    app(TenantContext::class)->setTenant($tenant);
    $tenant->profile()->firstOrFail()->update(['short_description' => 'Uređeni opis', 'email' => '']);
    $owner = User::query()->where('role', 'owner')->firstOrFail();
    $owner->forceFill(['email' => ' OWNER.LOTUS@EXAMPLE.TEST ', 'password' => 'Changed-password-1!',
        'is_active' => false, 'two_factor_secret' => encrypt('local-test-secret'),
        'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt('[]')])->save();
    $admin = PlatformAdmin::query()->sole();
    $admin->forceFill(['password' => 'Changed-platform-1!', 'is_active' => false])->save();
    $before = $owner->fresh()->getAttributes();

    $this->seed(LocalDemoSeeder::class);

    app(TenantContext::class)->setTenant($tenant);
    expect($owner->fresh()->getAttributes())->toBe($before);
    expect(Hash::check('Changed-platform-1!', $admin->fresh()->password))->toBeTrue();
    expect($admin->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('tenant_profiles', ['tenant_id' => $tenant->id, 'short_description' => 'Uređeni opis', 'email' => '']);
    $this->assertDatabaseCount('users', 8);
});

test('demo seed upgrades the foundation fixture without duplicating studios', function () {
    $this->seed(LocalFoundationSeeder::class);
    $ids = Tenant::query()->pluck('id')->all();

    $this->seed(LocalDemoSeeder::class);

    expect(Tenant::query()->pluck('id')->all())->toBe($ids);
    $this->assertDatabaseCount('users', 8);
    $this->assertDatabaseHas('tenant_profiles', ['email' => 'balance@example.test']);
});

test('demo seed rejects nonlocal environments even when forced', function (string $environment) {
    app()->detectEnvironment(fn (): string => $environment);

    try {
        expect(fn () => $this->artisan('db:seed', ['--class' => LocalDemoSeeder::class, '--force' => true])->run())
            ->toThrow(LogicException::class);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('platform_admins', 0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
})->with(['production', 'staging']);

test('a conflicting role rolls back the seed and clears context', function () {
    $this->seed(LocalFoundationSeeder::class);
    $tenant = Tenant::query()->where('design_key', 'balance')->firstOrFail();
    app(TenantContext::class)->setTenant($tenant);
    User::factory()->for($tenant)->create(['email' => 'owner.balance@example.test', 'role' => 'customer']);

    expect(fn () => $this->seed(LocalDemoSeeder::class))->toThrow(LogicException::class);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('platform_admins', 0);
    $this->assertDatabaseHas('tenant_profiles', ['tenant_id' => $tenant->id, 'short_description' => null]);
    expect(app(TenantContext::class)->tenant())->toBeNull();
});

test('demo seed does not verify an existing conflicting domain', function () {
    $this->seed(LocalFoundationSeeder::class);
    DB::table('tenant_domains')->where('normalized_hostname', 'balance.yoga.test')
        ->update(['verification_status' => 'pending', 'verified_at' => null]);

    expect(fn () => $this->seed(LocalDemoSeeder::class))->toThrow(LogicException::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseHas('tenant_domains', ['normalized_hostname' => 'balance.yoga.test', 'verification_status' => 'pending']);
    expect(app(TenantContext::class)->tenant())->toBeNull();
});

test('seeded customer uses only the password for the requested studio', function () {
    $this->seed(LocalDemoSeeder::class);
    $this->withCredentials();

    $this->postJson('http://balance.yoga.test/login', ['email' => 'customer@example.test', 'password' => 'Lotus-Demo-2026!'])
        ->assertUnprocessable();
    $response = $this->post('http://balance.yoga.test/login', ['email' => 'customer@example.test', 'password' => 'Balance-Demo-2026!'])
        ->assertRedirect('/account');
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue())
        ->get('http://balance.yoga.test/account')->assertOk();
    $this->getJson('http://lotus.yoga.test/account')->assertUnauthorized();
});

test('seeded administrators require and can complete real TOTP enrolment', function (string $base, string $email, string $password) {
    $this->seed(LocalDemoSeeder::class);
    $this->withCredentials();

    $response = $this->post($base.'/login', ['email' => $email, 'password' => $password]);
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
    $this->get($base.'/dashboard')->assertRedirect($base.'/settings/two-factor');
    $this->postJson($base.'/user/confirm-password', ['password' => $password])->assertCreated();
    $this->postJson($base.'/user/two-factor-authentication')->assertOk();
    $secret = $this->getJson($base.'/user/two-factor-secret-key')->assertOk()->json('secretKey');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $this->postJson($base.'/user/confirmed-two-factor-authentication', ['code' => $code])->assertOk();
    $this->get($base.'/dashboard')->assertOk();
})->with([
    ['http://lotus.yoga.test', 'owner.lotus@example.test', 'Lotus-Demo-2026!'],
    ['http://balance.yoga.test', 'owner.balance@example.test', 'Balance-Demo-2026!'],
    ['http://platform.yoga.test/platform', 'platform@example.test', 'Platform-Demo-2026!'],
]);
