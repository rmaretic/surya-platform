<?php

use App\Auth\TrustedAuthUrl;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withCredentials();
});

function authStudio(string $hostname, string $password = 'password', bool $verified = true): User
{
    $tenant = Tenant::factory()->active()->create(['name' => $hostname]);
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => $hostname, 'is_primary' => true]);
    $user = User::factory()->for($tenant)->create(['email' => 'same@example.test', 'password' => $password,
        'email_verified_at' => $verified ? now() : null]);
    app(TenantContext::class)->clear();

    return $user;
}

test('same email authenticates distinct studio identities with independent passwords and sessions', function () {
    $lotus = authStudio('lotus.yoga.test', 'Lotus-password1');
    $balance = authStudio('balance.yoga.test', 'Balance-password2');
    $first = $this->post('http://lotus.yoga.test/login', ['email' => $lotus->email, 'password' => 'Lotus-password1'])
        ->assertRedirect('/account');
    $lotusCookie = $first->getCookie(config('session.cookie'))->getValue();
    $this->withCookie(config('session.cookie'), $lotusCookie)->get('http://lotus.yoga.test/account')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $lotus->id));
    $this->getJson('http://balance.yoga.test/account')->assertUnauthorized();
    $this->postJson('http://balance.yoga.test/login', ['email' => $balance->email, 'password' => 'Lotus-password1'])
        ->assertUnprocessable();
    $second = $this->post('http://balance.yoga.test/login', ['email' => $balance->email, 'password' => 'Balance-password2'])
        ->assertRedirect('/account');
    $this->withCookie(config('session.cookie'), $second->getCookie(config('session.cookie'))->getValue())
        ->get('http://balance.yoga.test/account')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $balance->id));
    $this->assertDatabaseHas('tenant_sessions', ['id' => $lotusCookie, 'tenant_id' => $lotus->tenant_id, 'user_id' => $lotus->id]);
});

test('registration ignores injected tenant and owner role and sends a trusted verification link', function () {
    Notification::fake();
    $existing = authStudio('lotus.yoga.test');
    $other = authStudio('balance.yoga.test');
    $this->post('http://lotus.yoga.test/register', [
        'name' => 'New member', 'email' => 'new@example.test', 'password' => 'password123', 'password_confirmation' => 'password123',
        'tenant_id' => $other->tenant_id, 'role' => 'owner', 'is_active' => false,
    ])->assertRedirect('/account');
    $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'tenant_id' => $existing->tenant_id, 'role' => 'customer', 'is_active' => true]);
    app(TenantContext::class)->setTenant($existing->tenant);
    $member = User::query()->where('email', 'new@example.test')->firstOrFail();
    Notification::assertSentTo($member, VerifyEmail::class, function ($notification) use ($member): bool {
        expect(parse_url($notification->toMail($member)->actionUrl, PHP_URL_HOST))->toBe('lotus.yoga.test');

        return true;
    });
});

test('reset responses conceal account existence and tenant tokens cannot cross domains', function () {
    Notification::fake();
    $lotus = authStudio('lotus.yoga.test');
    $balance = authStudio('balance.yoga.test');
    $known = $this->postJson('http://lotus.yoga.test/forgot-password', ['email' => $lotus->email])->assertOk()->json();
    $unknown = $this->postJson('http://lotus.yoga.test/forgot-password', ['email' => 'absent@example.test'])->assertOk()->json();
    expect($unknown)->toBe($known);
    $notification = Notification::sent($lotus, ResetPassword::class)->sole();
    $this->assertDatabaseHas('tenant_password_reset_tokens', ['tenant_id' => $lotus->tenant_id, 'email' => $lotus->email]);
    $payload = ['email' => $lotus->email, 'token' => $notification->token,
        'password' => 'New-password123', 'password_confirmation' => 'New-password123'];
    $this->postJson('http://balance.yoga.test/reset-password', $payload)->assertUnprocessable();
    $this->post('http://lotus.yoga.test/reset-password', $payload)->assertRedirect('/login');
    $this->postJson('http://lotus.yoga.test/reset-password', $payload)->assertUnprocessable();
    expect(Hash::check('password', DB::table('users')->where('id', $balance->id)->value('password')))->toBeTrue();
    expect(Hash::check('New-password123', DB::table('users')->where('id', $lotus->id)->value('password')))->toBeTrue();
});

test('platform authentication has no public registration and uses separate identities and reset storage', function () {
    Notification::fake();
    authStudio('lotus.yoga.test');
    $admin = PlatformAdmin::factory()->create(['email' => 'same@example.test', 'password' => 'Admin-password1']);
    $this->get('http://platform.yoga.test/platform/register')->assertNotFound();
    $this->postJson('http://platform.yoga.test/platform/register', [])->assertNotFound();
    $this->postJson('http://platform.yoga.test/platform/login', ['email' => $admin->email, 'password' => 'password'])->assertUnprocessable();
    $response = $this->post('http://platform.yoga.test/platform/login', ['email' => $admin->email, 'password' => 'Admin-password1'])->assertRedirect('/platform/account');
    $id = $response->getCookie(config('session.cookie'))->getValue();
    $this->assertDatabaseHas('platform_sessions', ['id' => $id, 'user_id' => $admin->id]);
    $this->withCookie(config('session.cookie'), $id)->get('http://platform.yoga.test/platform/account')
        ->assertRedirect('http://platform.yoga.test/platform/settings/two-factor');
    $this->getJson('http://lotus.yoga.test/account')->assertUnauthorized();
    $this->withCookie(config('session.cookie'), '');
    $this->postJson('http://platform.yoga.test/platform/forgot-password', ['email' => $admin->email])->assertOk();
    Notification::assertSentTo($admin, ResetPassword::class);
    $this->assertDatabaseHas('platform_password_reset_tokens', ['email' => $admin->email]);
});

test('signed verification rejects a different hostname and verifies only the current identity', function () {
    $lotus = authStudio('lotus.yoga.test', verified: false);
    authStudio('balance.yoga.test', verified: false);
    $url = app(TrustedAuthUrl::class)->verification($lotus);
    $response = $this->post('http://lotus.yoga.test/login', ['email' => $lotus->email, 'password' => 'password'])->assertRedirect('/account');
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
    $this->get('http://lotus.yoga.test/account')->assertRedirect('http://lotus.yoga.test/email/verify');
    $this->get(str_replace('lotus.yoga.test', 'balance.yoga.test', $url))->assertRedirect('http://balance.yoga.test/login');
    $this->get($url)->assertRedirect('/account');
    expect(DB::table('users')->where('id', $lotus->id)->value('email_verified_at'))->not->toBeNull();
});

test('login rotates the session and logout invalidates it with host-only cookies', function () {
    $user = authStudio('lotus.yoga.test');
    $guest = $this->get('http://lotus.yoga.test/login')->assertOk();
    $old = $guest->getCookie(config('session.cookie'))->getValue();
    $response = $this->withCookie(config('session.cookie'), $old)->post('http://lotus.yoga.test/login', ['email' => $user->email, 'password' => 'password']);
    $cookie = $response->getCookie(config('session.cookie'));
    expect($cookie->getValue())->not->toBe($old);
    expect($cookie->getDomain())->toBeNull();
    expect($cookie->isHttpOnly())->toBeTrue();
    $this->withCookie(config('session.cookie'), $cookie->getValue())->post('http://lotus.yoga.test/logout')->assertRedirect('/');
    $this->getJson('http://lotus.yoga.test/account')->assertUnauthorized();
    $this->assertDatabaseMissing('tenant_sessions', ['id' => $cookie->getValue()]);
});

test('inactive users cannot authenticate or continue an existing session', function () {
    $user = authStudio('lotus.yoga.test');
    $response = $this->post('http://lotus.yoga.test/login', ['email' => $user->email, 'password' => 'password']);
    DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue())
        ->getJson('http://lotus.yoga.test/account')->assertUnauthorized();
    $this->postJson('http://lotus.yoga.test/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
});

test('login rate limits are independent between studios', function () {
    authStudio('lotus.yoga.test');
    authStudio('balance.yoga.test');
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('http://lotus.yoga.test/login', ['email' => 'same@example.test', 'password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson('http://lotus.yoga.test/login', ['email' => 'same@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    $this->postJson('http://balance.yoga.test/login', ['email' => 'same@example.test', 'password' => 'password'])->assertOk();
});

test('registration accepts the same email in another studio but rejects duplicates locally', function () {
    Notification::fake();
    authStudio('lotus.yoga.test');
    $balance = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($balance);
    TenantDomain::factory()->verified()->for($balance)->create(['hostname' => 'balance.yoga.test', 'is_primary' => true]);
    app(TenantContext::class)->clear();
    $data = ['name' => 'Member', 'email' => 'SAME@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'];
    $this->postJson('http://lotus.yoga.test/register', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('http://balance.yoga.test/register', $data)->assertCreated();
});

test('browser posts require a matching CSRF token', function () {
    $user = authStudio('lotus.yoga.test');
    app()->detectEnvironment(fn () => 'local');
    try {
        $response = $this->get('http://lotus.yoga.test/login')->assertOk();
        $token = app('session')->token();
        $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
        $this->postJson('http://lotus.yoga.test/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(419);
        $this->postJson('http://lotus.yoga.test/login', ['email' => $user->email, 'password' => 'password', '_token' => $token])->assertOk();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

test('a platform reset token cannot reset a studio account with the same email', function () {
    Notification::fake();
    $user = authStudio('lotus.yoga.test');
    $admin = PlatformAdmin::factory()->create(['email' => $user->email]);
    $this->postJson('http://platform.yoga.test/platform/forgot-password', ['email' => $admin->email])->assertOk();
    $token = Notification::sent($admin, ResetPassword::class)->sole()->token;
    $payload = ['email' => $user->email, 'token' => $token, 'password' => 'new-password123', 'password_confirmation' => 'new-password123'];
    $this->postJson('http://lotus.yoga.test/reset-password', $payload)->assertUnprocessable();
    $this->post('http://platform.yoga.test/platform/reset-password', $payload)->assertRedirect('/platform/login');
    expect(Hash::check('new-password123', $admin->fresh()->password))->toBeTrue();
    expect(Hash::check('password', DB::table('users')->where('id', $user->id)->value('password')))->toBeTrue();
});

test('expired reset and verification links are rejected', function () {
    Notification::fake();
    $user = authStudio('lotus.yoga.test', verified: false);
    $url = app(TrustedAuthUrl::class)->verification($user);
    $this->postJson('http://lotus.yoga.test/forgot-password', ['email' => $user->email])->assertOk();
    $token = Notification::sent($user, ResetPassword::class)->sole()->token;
    $this->travel(61)->minutes();
    $this->postJson('http://lotus.yoga.test/reset-password', ['email' => $user->email, 'token' => $token,
        'password' => 'new-password123', 'password_confirmation' => 'new-password123'])->assertUnprocessable();
    $this->signInToStudio($user)->get($url)->assertForbidden();
    expect(DB::table('users')->where('id', $user->id)->value('email_verified_at'))->toBeNull();
});

test('remember cookies cannot authenticate the same email in another studio', function () {
    $user = authStudio('lotus.yoga.test');
    authStudio('balance.yoga.test');
    $response = $this->post('http://lotus.yoga.test/login', ['email' => $user->email, 'password' => 'password', 'remember' => true]);
    $cookieName = Auth::guard('web')->getRecallerName();
    $cookie = $response->getCookie($cookieName)->getValue();
    $this->withCookie($cookieName, $cookie)->getJson('http://balance.yoga.test/account')->assertUnauthorized();
    $this->get('http://lotus.yoga.test/account')->assertOk();
});

test('trusted notification URLs ignore forwarded hosts and aliases', function () {
    Notification::fake();
    $user = authStudio('lotus.yoga.test');
    app(TenantContext::class)->setTenant($user->tenant);
    TenantDomain::factory()->verified()->for($user->tenant)->create(['hostname' => 'alias.yoga.test']);
    app(TenantContext::class)->clear();
    $this->postJson('http://alias.yoga.test/forgot-password', ['email' => $user->email],
        ['X-Forwarded-Host' => 'attacker.test'])->assertOk();
    $notification = Notification::sent($user, ResetPassword::class)->sole();
    expect(parse_url($notification->toMail($user)->actionUrl, PHP_URL_HOST))->toBe('lotus.yoga.test');
});
