<?php

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\User;
use App\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

function twoFactorIdentity(TestCase $test, string $role, bool $enrolled = false): User|PlatformAdmin
{
    $attributes = $enrolled ? [
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['single-use-recovery', 'second-recovery'])),
        'two_factor_confirmed_at' => now(),
    ] : [];
    if ($role === 'platform') {
        return PlatformAdmin::factory()->create($attributes);
    }

    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test', 'is_primary' => true]);

    return User::factory()->for($tenant)->create(['role' => $role, ...$attributes]);
}

function retainTwoFactorSession(TestCase $test, TestResponse $response): void
{
    $test->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue())->withCredentials();
}

function twoFactorBase(string $role): string
{
    return $role === 'platform' ? 'http://platform.yoga.test/platform' : 'http://lotus.yoga.test';
}

test('M1 role matrix restricts platform, studio, profile and staff abilities', function (string $role) {
    $user = twoFactorIdentity($this, $role, true);
    $tenant = $user instanceof User ? $user->tenant : Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    $profile = TenantProfile::factory()->for($tenant)->create();
    $staff = User::factory()->for($tenant)->create(['role' => 'manager']);
    $gate = Gate::forUser($user);
    expect($gate->allows('studio-administration'))->toBe(in_array($role, ['owner', 'manager', 'instructor'], true));
    expect($gate->allows('customer-portal'))->toBe($role === 'customer');
    expect($gate->allows('update', $profile))->toBe($role === 'owner');
    expect($gate->allows('viewAny', User::class))->toBe($role === 'owner');
    expect($gate->allows('invite', [User::class, 'manager']))->toBe($role === 'owner');
    expect($gate->allows('invite', [User::class, 'instructor']))->toBe($role === 'owner');
    expect($gate->allows('invite', [User::class, 'owner']))->toBeFalse();
    expect($gate->allows('invite', [User::class, 'customer']))->toBeFalse();
    expect($gate->allows('updateRole', [$staff, 'instructor']))->toBe($role === 'owner');
    expect($gate->allows('updateRole', [$staff, 'owner']))->toBeFalse();
    expect($gate->allows('deactivate', $staff))->toBe($role === 'owner');
    $staff->role = 'owner';
    expect($gate->allows('deactivate', $staff))->toBeFalse();
    $staff->role = 'customer';
    expect($gate->allows('deactivate', $staff))->toBeFalse();
    expect($gate->allows('viewAny', Tenant::class))->toBeFalse();
    app(TenantContext::class)->setPlatform();
    foreach (['viewAny', 'create'] as $ability) {
        expect($gate->allows($ability, Tenant::class))->toBe($role === 'platform');
    }
    foreach (['view', 'update'] as $ability) {
        expect($gate->allows($ability, $tenant))->toBe($role === 'platform');
    }
    expect($gate->allows('studio-administration'))->toBeFalse();
    expect($gate->allows('update', $profile))->toBeFalse();
})->with(['owner', 'manager', 'instructor', 'customer', 'platform']);

test('required enrolment blocks account and administrative abilities until real TOTP confirmation', function (string $role) {
    Log::spy();
    $user = twoFactorIdentity($this, $role);
    $base = twoFactorBase($role);
    $response = $this->post($base.'/login', ['email' => $user->email, 'password' => 'password']);
    retainTwoFactorSession($this, $response);
    $this->get($base.'/account')->assertRedirect($base.'/settings/two-factor');
    $this->get($base.'/dashboard')->assertRedirect($base.'/settings/two-factor');
    $this->get($base.'/settings/two-factor')->assertRedirect($base.'/user/confirm-password');
    $this->postJson($base.'/user/two-factor-authentication')->assertStatus(423);
    $this->postJson($base.'/user/confirm-password', ['password' => 'incorrect'])->assertUnprocessable();
    $this->postJson($base.'/user/confirm-password', ['password' => 'password'])->assertCreated();
    $this->get($base.'/settings/two-factor')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/TwoFactorSettings')->where('twoFactorRequired', true)->where('twoFactorEnabled', false)
        ->missing('two_factor_secret')->missing('auth.user.two_factor_secret')->missing('auth.user.two_factor_recovery_codes'));
    $this->postJson($base.'/user/two-factor-authentication')->assertOk();
    $secret = $this->getJson($base.'/user/two-factor-secret-key')->assertOk()->json('secretKey');
    $this->getJson($base.'/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg', 'url']);
    $this->postJson($base.'/user/confirmed-two-factor-authentication', ['code' => 'wrong'])->assertUnprocessable();
    $this->get($base.'/dashboard')->assertRedirect($base.'/settings/two-factor');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $this->postJson($base.'/user/confirmed-two-factor-authentication', ['code' => $code])->assertOk();
    $this->get($base.'/dashboard')->assertOk();
    $codes = $this->getJson($base.'/user/two-factor-recovery-codes')->assertOk()->json();
    expect($codes)->toHaveCount(8);
    $this->deleteJson($base.'/user/two-factor-authentication')->assertForbidden();
    $this->postJson($base.'/user/two-factor-recovery-codes')->assertOk();
    expect($this->getJson($base.'/user/two-factor-recovery-codes')->json())->not->toBe($codes);
    $table = $role === 'platform' ? 'platform_admins' : 'users';
    $stored = DB::table($table)->where('id', $user->id)->first();
    expect($stored->two_factor_confirmed_at)->not->toBeNull();
    expect($stored->two_factor_secret)->not->toBe($secret);
    $payload = $this->get($base.'/settings/two-factor')->getContent();
    expect($payload)->not->toContain($secret, $codes[0], $stored->two_factor_secret);
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
})->with(['owner', 'platform']);

test('recovery code authenticates exactly once and platform challenge redirects stay in their area', function (string $role) {
    $user = twoFactorIdentity($this, $role, true);
    $base = twoFactorBase($role);
    $this->get($base.'/two-factor-challenge')->assertRedirect($base.'/login');
    $response = $this->post($base.'/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($base.'/two-factor-challenge');
    retainTwoFactorSession($this, $response);
    $this->get($base.'/two-factor-challenge')->assertOk();
    $this->get($base.'/dashboard')->assertRedirect($base.'/login');
    $this->post($base.'/two-factor-challenge', ['recovery_code' => 'incorrect'])->assertRedirect($base.'/two-factor-challenge');
    $response = $this->post($base.'/two-factor-challenge', ['recovery_code' => 'single-use-recovery'])
        ->assertRedirect($base.'/dashboard');
    retainTwoFactorSession($this, $response);
    $this->get($base.'/dashboard')->assertOk();
    $table = $role === 'platform' ? 'platform_admins' : 'users';
    expect(decrypt(DB::table($table)->where('id', $user->id)->value('two_factor_recovery_codes')))->not->toContain('single-use-recovery');
    $this->post($base.'/logout');
    $response = $this->post($base.'/login', ['email' => $user->email, 'password' => 'password']);
    retainTwoFactorSession($this, $response);
    $this->postJson($base.'/two-factor-challenge', ['recovery_code' => 'single-use-recovery'])->assertUnprocessable();
    $this->assertGuest($role === 'platform' ? 'platform' : 'web');
})->with(['owner', 'platform']);

test('wrong challenge codes are rate limited', function (string $role) {
    $user = twoFactorIdentity($this, $role, true);
    $base = twoFactorBase($role);
    retainTwoFactorSession($this, $this->post($base.'/login', ['email' => $user->email, 'password' => 'password']));
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson($base.'/two-factor-challenge', ['code' => 'invalid'])->assertUnprocessable();
    }
    $this->postJson($base.'/two-factor-challenge', ['recovery_code' => 'single-use-recovery'])->assertTooManyRequests();
    $this->assertGuest($role === 'platform' ? 'platform' : 'web');
})->with(['owner', 'platform']);

test('a deactivated identity cannot complete a pending challenge', function (string $role) {
    $user = twoFactorIdentity($this, $role, true);
    $base = twoFactorBase($role);
    retainTwoFactorSession($this, $this->post($base.'/login', ['email' => $user->email, 'password' => 'password']));
    DB::table($role === 'platform' ? 'platform_admins' : 'users')->where('id', $user->id)->update(['is_active' => false]);
    $this->post($base.'/two-factor-challenge', ['recovery_code' => 'single-use-recovery'])->assertRedirect($base.'/login');
    $this->assertGuest($role === 'platform' ? 'platform' : 'web');
})->with(['owner', 'platform']);

test('studio routes enforce roles through HTTP', function (string $role, int $dashboard, int $portal) {
    $user = twoFactorIdentity($this, $role);
    $this->signInToStudio($user);
    $this->get('http://lotus.yoga.test/dashboard')->assertStatus($dashboard);
    $this->get('http://lotus.yoga.test/portal')->assertStatus($portal);
    $this->get('http://lotus.yoga.test/platform/dashboard')->assertNotFound();
})->with([['manager', 200, 403], ['instructor', 200, 403], ['customer', 403, 200]]);

test('missing 2FA removes administrative privileges even outside HTTP', function (string $role) {
    $user = twoFactorIdentity($this, $role);
    if ($role === 'platform') {
        app(TenantContext::class)->setPlatform();
        expect(Gate::forUser($user)->allows('viewAny', Tenant::class))->toBeFalse();
        expect(Gate::forUser($user)->allows('create', Tenant::class))->toBeFalse();
    } else {
        $profile = TenantProfile::factory()->for($user->tenant)->create();
        expect(Gate::forUser($user)->allows('update', $profile))->toBeFalse();
        expect(Gate::forUser($user)->allows('viewAny', User::class))->toBeFalse();
        expect(Gate::forUser($user)->allows('studio-administration'))->toBeFalse();
    }
})->with(['owner', 'platform']);

test('TOTP login and replay protection are isolated by studio and identity', function () {
    $lotus = twoFactorIdentity($this, 'owner', true);
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'balance.yoga.test', 'is_primary' => true]);
    $balance = User::factory()->for($tenant)->create([
        'role' => 'owner', 'email' => $lotus->email,
        'two_factor_secret' => $lotus->two_factor_secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => encrypt(json_encode(['balance-recovery'])),
    ]);
    $code = app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP');
    $first = $this->post('http://lotus.yoga.test/login', ['email' => $lotus->email, 'password' => 'password']);
    retainTwoFactorSession($this, $first);
    $this->post('http://balance.yoga.test/two-factor-challenge', ['code' => $code])
        ->assertRedirect('http://balance.yoga.test/login');
    $this->assertGuest();
    retainTwoFactorSession($this, $first);
    $response = $this->post('http://lotus.yoga.test/two-factor-challenge', ['code' => $code])->assertRedirect('/account');
    retainTwoFactorSession($this, $response);
    $this->get('http://lotus.yoga.test/dashboard')->assertOk();
    $this->post('http://lotus.yoga.test/logout');
    retainTwoFactorSession($this, $this->post('http://lotus.yoga.test/login', ['email' => $lotus->email, 'password' => 'password']));
    $this->postJson('http://lotus.yoga.test/two-factor-challenge', ['code' => $code])->assertUnprocessable();
    retainTwoFactorSession($this, $this->post('http://balance.yoga.test/login', ['email' => $balance->email, 'password' => 'password']));
    $response = $this->post('http://balance.yoga.test/two-factor-challenge', ['code' => $code])->assertRedirect('/account');
    retainTwoFactorSession($this, $response);
    $this->get('http://balance.yoga.test/dashboard')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $balance->id));
});

test('sensitive settings require fresh password confirmation and do not flash codes', function (string $role) {
    config(['auth.password_timeout' => 60]);
    $user = twoFactorIdentity($this, $role);
    $base = twoFactorBase($role);
    retainTwoFactorSession($this, $this->post($base.'/login', ['email' => $user->email, 'password' => 'password']));
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson($base.'/user/confirm-password', ['password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson($base.'/user/confirm-password', ['password' => 'password'])->assertTooManyRequests();
    $this->travel(61)->seconds();
    $this->postJson($base.'/user/confirm-password', ['password' => 'password'])->assertCreated();
    $this->postJson($base.'/user/two-factor-authentication')->assertOk();
    $this->from($base.'/settings/two-factor')->post($base.'/user/confirmed-two-factor-authentication', ['code' => 'private-invalid-code'])
        ->assertRedirect($base.'/settings/two-factor');
    if ($user instanceof User) {
        app(TenantContext::class)->setTenant($user->tenant);
    } else {
        app(TenantContext::class)->setPlatform();
    }
    expect(session()->getOldInput('code'))->toBeNull();
    $this->travel(config('auth.password_timeout') + 1)->seconds();
    foreach (['two-factor-qr-code', 'two-factor-secret-key', 'two-factor-recovery-codes'] as $endpoint) {
        $this->getJson($base.'/user/'.$endpoint)->assertStatus(423);
    }
    $this->postJson($base.'/user/two-factor-recovery-codes')->assertStatus(423);
})->with(['owner', 'platform']);

test('enrolment code guessing is throttled', function () {
    $user = twoFactorIdentity($this, 'owner');
    $this->signInToStudio($user);
    $base = twoFactorBase('owner');
    $this->postJson($base.'/user/confirm-password', ['password' => 'password'])->assertCreated();
    $this->postJson($base.'/user/two-factor-authentication')->assertOk();
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->postJson($base.'/user/confirmed-two-factor-authentication', ['code' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson($base.'/user/confirmed-two-factor-authentication', ['code' => 'wrong'])->assertTooManyRequests();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'two_factor_confirmed_at' => null]);
});

test('optional 2FA can be disabled after password confirmation', function () {
    $user = twoFactorIdentity($this, 'manager', true);
    retainTwoFactorSession($this, $this->post('http://lotus.yoga.test/login', ['email' => $user->email, 'password' => 'password']));
    retainTwoFactorSession($this, $this->post('http://lotus.yoga.test/two-factor-challenge', ['recovery_code' => 'single-use-recovery']));
    $this->deleteJson('http://lotus.yoga.test/user/two-factor-authentication')->assertStatus(423);
    $this->postJson('http://lotus.yoga.test/user/confirm-password', ['password' => 'password'])->assertCreated();
    $this->deleteJson('http://lotus.yoga.test/user/two-factor-authentication')->assertOk();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null]);
});

test('staff policies reject cross studio records and inactive owners', function () {
    $owner = twoFactorIdentity($this, 'owner', true);
    $staff = User::factory()->for($owner->tenant)->create(['role' => 'instructor']);
    $other = Tenant::factory()->active()->create();
    $staff->tenant_id = $other->id;
    expect(Gate::forUser($owner)->allows('deactivate', $staff))->toBeFalse();
    expect(Gate::forUser($owner)->allows('updateRole', [$staff, 'manager']))->toBeFalse();
    $owner->is_active = false;
    expect(Gate::forUser($owner)->allows('viewAny', User::class))->toBeFalse();
    expect(Gate::forUser($owner)->allows('studio-administration'))->toBeFalse();
});

test('malformed sensitive input returns validation errors without changing security state', function (array $payload, string $endpoint, string $field) {
    $user = twoFactorIdentity($this, 'owner');
    $this->signInToStudio($user);
    $this->postJson('http://lotus.yoga.test/user/confirm-password', ['password' => 'password'])->assertCreated();
    $this->postJson('http://lotus.yoga.test/user/'.$endpoint, $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
})->with([
    [['code' => ['unexpected']], 'confirmed-two-factor-authentication', 'code'],
    [['password' => ['unexpected']], 'confirm-password', 'password'],
]);
