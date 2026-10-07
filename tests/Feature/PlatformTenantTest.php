<?php

use App\Actions\ManagePlatformTenant;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Notifications\OwnerInvitationNotification;
use App\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

function platformOperator(TestCase $test): PlatformAdmin
{
    $admin = PlatformAdmin::factory()->create([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['platform-test-recovery'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $response = $test->post('http://platform.yoga.test/platform/login', ['email' => $admin->email, 'password' => 'password']);
    $test->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue())->withCredentials();
    $response = $test->post('http://platform.yoga.test/platform/two-factor-challenge', ['recovery_code' => 'platform-test-recovery']);
    $response->assertRedirect('http://platform.yoga.test/platform/account');
    $test->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());

    return $admin;
}

/** @return array<string, string> */
function platformStudioData(): array
{
    return ['name' => 'Novi studio', 'hostname' => 'new.yoga.test', 'design_key' => 'lotus',
        'owner_email' => 'owner@example.test', 'email' => 'contact@example.test', 'phone' => '012345',
        'address' => 'Adresa 1', 'short_description' => 'Javni opis'];
}

function createPlatformStudio(TestCase $test): Tenant
{
    $test->post('http://platform.yoga.test/platform/tenants', platformStudioData())->assertRedirect();

    return Tenant::query()->where('name', 'Novi studio')->sole();
}

function verifyPlatformStudio(TestCase $test, Tenant $tenant): void
{
    $domainId = DB::table('tenant_domains')->where('tenant_id', $tenant->id)->value('id');
    $test->post("http://platform.yoga.test/platform/tenants/{$tenant->id}/domains/{$domainId}/verify",
        ['reference' => 'OPS-123', 'confirmed' => true])->assertRedirect();
}

test('platform operator sees real counts and can search paginated studios by name and domain', function () {
    platformOperator($this);
    Tenant::factory()->active()->count(26)->create();
    $tenant = createPlatformStudio($this);

    $this->get('http://platform.yoga.test/platform/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('platform/Tenants')->where('counts.active', 26)->where('counts.pending', 1)
        ->where('counts.suspended', 0)->where('tenants.total', 27)->has('tenants.data', 25));
    $this->get('http://platform.yoga.test/platform/tenants?search=new.yoga.test')->assertInertia(fn (Assert $page) => $page
        ->has('tenants.data', 1)->where('tenants.data.0.id', $tenant->id));
    $this->get('http://platform.yoga.test/platform/tenants?search=Novi')->assertInertia(fn (Assert $page) => $page->where('tenants.total', 1));
    $this->get('http://platform.yoga.test/platform/tenants?search=absent')->assertInertia(fn (Assert $page) => $page->has('tenants.data', 0));
});

test('creation atomically prepares a profile pending domain and unsent owner invitation without trusting privileged input', function () {
    Notification::fake();
    $admin = platformOperator($this);
    $this->post('http://platform.yoga.test/platform/tenants', [
        ...platformStudioData(), 'hostname' => 'NEW.YOGA.TEST.', 'owner_email' => 'OWNER@EXAMPLE.TEST',
        'status' => 'active', 'verification_status' => 'verified', 'tenant_id' => 99,
        'token_hash' => 'injected-secret', 'role' => 'manager', 'actor_platform_admin_id' => 99,
    ])->assertRedirect();
    $tenant = Tenant::query()->sole();

    $this->assertDatabaseHas('tenant_profiles', ['tenant_id' => $tenant->id, 'name' => 'Novi studio', 'phone' => '012345']);
    $this->assertDatabaseHas('tenant_domains', ['tenant_id' => $tenant->id, 'hostname' => 'new.yoga.test', 'verification_status' => 'pending', 'verified_at' => null]);
    $this->assertDatabaseHas('staff_invitations', ['tenant_id' => $tenant->id, 'email' => 'owner@example.test', 'role' => 'owner', 'sent_at' => null, 'invited_by_platform_admin_id' => $admin->id]);
    $this->assertDatabaseHas('platform_audit_logs', ['target_tenant_id' => $tenant->id, 'actor_platform_admin_id' => $admin->id, 'action' => 'tenant.created']);
    $audit = PlatformAuditLog::query()->sole();
    expect($audit->created_at)->not->toBeNull();
    expect(json_encode($audit->changes))->not->toContain('token', 'password', 'injected-secret');
    $this->get("http://platform.yoga.test/platform/tenants/{$tenant->id}")->assertInertia(fn (Assert $page) => $page
        ->component('platform/TenantDetail')->where('profile.phone', '012345')->missing('invitation.token_hash'));
    $this->postJson("http://platform.yoga.test/platform/tenants/{$tenant->id}/owner-invitation")->assertUnprocessable()->assertJsonValidationErrors('invitation');
    $this->get('http://new.yoga.test/')->assertNotFound();
    Notification::assertNothingSent();
});

test('creation rejects invalid designs hosts and profile values without partial records', function (array $invalid, string $field) {
    platformOperator($this);

    $this->postJson('http://platform.yoga.test/platform/tenants', [...platformStudioData(), ...$invalid])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('tenants', 0);
    $this->assertDatabaseCount('staff_invitations', 0);
    $this->assertDatabaseCount('platform_audit_logs', 0);
})->with([
    'component path' => [['design_key' => '../../PlatformAdmin'], 'design_key'],
    'non-string design' => [['design_key' => ['lotus']], 'design_key'],
    'platform hostname' => [['hostname' => 'platform.yoga.test'], 'hostname'],
    'url instead of hostname' => [['hostname' => 'https://new.yoga.test/a'], 'hostname'],
    'non-string hostname' => [['hostname' => ['new.yoga.test']], 'hostname'],
    'invalid owner' => [['owner_email' => 'invalid'], 'owner_email'],
    'invalid public email' => [['email' => 'invalid'], 'email'],
    'long description' => [['short_description' => str_repeat('x', 1001)], 'short_description'],
    'missing name' => [['name' => ''], 'name'],
]);

test('a database uniqueness collision rolls back all newly created records and restores platform context', function () {
    $admin = platformOperator($this);
    createPlatformStudio($this);
    app(TenantContext::class)->setPlatform();

    expect(fn () => app(ManagePlatformTenant::class)->create($admin, platformStudioData()))
        ->toThrow(ValidationException::class);
    expect(app(TenantContext::class)->isPlatform())->toBeTrue();
    $this->assertDatabaseCount('tenants', 1);
    $this->assertDatabaseCount('tenant_profiles', 1);
    $this->assertDatabaseCount('staff_invitations', 1);
    $this->assertDatabaseCount('platform_audit_logs', 1);
});

test('domain verification requires evidence and scopes the domain to its target studio', function () {
    $admin = platformOperator($this);
    $tenant = createPlatformStudio($this);
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    $otherDomain = TenantDomain::factory()->for($other)->create();
    $domainId = DB::table('tenant_domains')->where('tenant_id', $tenant->id)->value('id');
    $base = "http://platform.yoga.test/platform/tenants/{$tenant->id}/domains";

    $this->postJson("$base/$domainId/verify", ['confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('reference');
    $this->postJson("$base/$domainId/verify", ['reference' => 'OPS-123'])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->postJson("$base/$domainId/verify", ['reference' => 'https://secret.test/?token=secret', 'confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('reference');
    $this->postJson("$base/{$otherDomain->id}/verify", ['reference' => 'OPS-123', 'confirmed' => true])->assertNotFound();
    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'pending']);
    verifyPlatformStudio($this, $tenant);
    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
    $this->assertDatabaseHas('platform_audit_logs', ['action' => 'domain.verified', 'actor_platform_admin_id' => $admin->id, 'subject_id' => $domainId]);
    expect(PlatformAuditLog::query()->where('action', 'domain.verified')->sole()->changes['reference'])->toBe('OPS-123');
    $this->get('http://new.yoga.test/')->assertOk();
});

test('design and additional pending domains are validated and audited', function () {
    platformOperator($this);
    $tenant = createPlatformStudio($this);
    $base = "http://platform.yoga.test/platform/tenants/{$tenant->id}";

    $this->patchJson($base, ['design_key' => '../unknown'])->assertUnprocessable();
    $this->patch($base, ['design_key' => 'balance', 'status' => 'active'])->assertRedirect();
    $this->post("$base/domains", ['hostname' => 'ALIAS.YOGA.TEST.'])->assertRedirect();
    $this->postJson("$base/domains", ['hostname' => 'alias.yoga.test'])->assertUnprocessable()->assertJsonValidationErrors('hostname');
    $this->postJson("$base/domains", ['hostname' => 'platform.yoga.test'])->assertUnprocessable();
    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'design_key' => 'balance', 'status' => 'pending']);
    $this->assertDatabaseHas('tenant_domains', ['tenant_id' => $tenant->id, 'hostname' => 'alias.yoga.test', 'verification_status' => 'pending', 'is_primary' => false]);
    $this->assertDatabaseHas('platform_audit_logs', ['action' => 'tenant.design_changed', 'target_tenant_id' => $tenant->id]);
    $this->assertDatabaseHas('platform_audit_logs', ['action' => 'domain.created', 'target_tenant_id' => $tenant->id]);
    $this->get('http://alias.yoga.test/')->assertNotFound();
});

test('suspension is confirmed audited and blocks the studio without deleting its records', function () {
    platformOperator($this);
    $tenant = createPlatformStudio($this);
    verifyPlatformStudio($this, $tenant);
    $base = "http://platform.yoga.test/platform/tenants/{$tenant->id}";
    $this->postJson("$base/suspend", ['reference' => 'OPS-124'])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->post("$base/suspend", ['reference' => 'OPS-124', 'confirmed' => true])->assertRedirect();

    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended']);
    $this->assertDatabaseCount('tenant_profiles', 1);
    $this->assertDatabaseCount('staff_invitations', 1);
    $this->assertDatabaseHas('platform_audit_logs', ['action' => 'tenant.suspended', 'target_tenant_id' => $tenant->id]);
    $this->postJson("$base/owner-invitation")->assertUnprocessable();
    $this->get('http://new.yoga.test/')->assertServiceUnavailable();
    $this->get('http://new.yoga.test/login')->assertServiceUnavailable();
});

test('platform routes require a verified enrolled platform identity on the platform domain', function () {
    $this->get('http://platform.yoga.test/platform/tenants')->assertRedirect('http://platform.yoga.test/platform/login');
    $this->post('http://platform.yoga.test/platform/tenants', platformStudioData())->assertRedirect('http://platform.yoga.test/platform/login');
    $admin = PlatformAdmin::factory()->create();
    $response = $this->post('http://platform.yoga.test/platform/login', ['email' => $admin->email, 'password' => 'password']);
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue())->withCredentials();
    $this->get('http://platform.yoga.test/platform/tenants')->assertRedirect('http://platform.yoga.test/platform/settings/two-factor');
    $this->assertDatabaseCount('tenants', 0);
});

test('studio personnel cannot read or mutate the platform registry', function (string $role) {
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test']);
    $user = User::factory()->for($tenant)->create(['role' => $role]);
    $this->actingAs($user, 'web');

    $this->get('http://lotus.yoga.test/platform/tenants')->assertNotFound();
    $this->post('http://lotus.yoga.test/platform/tenants', platformStudioData())->assertNotFound();
    $this->get('http://platform.yoga.test/platform/tenants')->assertRedirect('http://platform.yoga.test/platform/login');
    $this->post("http://platform.yoga.test/platform/tenants/{$tenant->id}/suspend", ['reference' => 'OPS-124', 'confirmed' => true])
        ->assertRedirect('http://platform.yoga.test/platform/login');
    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
})->with(['owner', 'manager', 'instructor', 'customer']);

test('owner delivery rotates the token and acceptance is tenant bound email bound and single use', function () {
    Notification::fake();
    $this->freezeTime();
    platformOperator($this);
    $tenant = createPlatformStudio($this);
    verifyPlatformStudio($this, $tenant);
    $base = "http://platform.yoga.test/platform/tenants/{$tenant->id}";
    $this->post("$base/owner-invitation")->assertRedirect();
    $first = Notification::sent(new AnonymousNotifiable, OwnerInvitationNotification::class)->first();
    parse_str(parse_url($first->acceptUrl, PHP_URL_FRAGMENT), $firstFragment);
    $this->post("$base/owner-invitation")->assertRedirect();
    $second = Notification::sent(new AnonymousNotifiable, OwnerInvitationNotification::class)->last();
    parse_str(parse_url($second->acceptUrl, PHP_URL_FRAGMENT), $fragment);
    $invitationId = DB::table('staff_invitations')->where('tenant_id', $tenant->id)->value('id');
    $path = "/owner-invitations/$invitationId";
    $this->post('http://platform.yoga.test/platform/logout');
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    TenantDomain::factory()->verified()->for($other)->create(['hostname' => 'other.yoga.test']);
    $data = ['token' => $fragment['token'], 'name' => 'Vlasnik', 'password' => 'Owner-password1!', 'password_confirmation' => 'Owner-password1!',
        'email' => 'attacker@example.test', 'role' => 'manager', 'tenant_id' => $other->id];

    $this->get("http://other.yoga.test$path")->assertNotFound();
    $this->postJson("http://other.yoga.test$path", $data)->assertNotFound();
    $this->get("http://new.yoga.test$path")->assertInertia(fn (Assert $page) => $page->component('auth/AcceptOwnerInvitation')
        ->where('invitationId', $invitationId)->missing('token')->missing('email'));
    $this->postJson("http://new.yoga.test$path", [...$data, 'token' => $firstFragment['token']])->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->post("http://new.yoga.test$path", $data)->assertRedirect('http://new.yoga.test/login');
    $this->postJson("http://new.yoga.test$path", $data)->assertNotFound();
    $this->assertDatabaseHas('users', ['tenant_id' => $tenant->id, 'email' => 'owner@example.test', 'role' => 'owner', 'two_factor_confirmed_at' => null]);
    $this->assertDatabaseMissing('users', ['email' => 'attacker@example.test']);
    $this->assertDatabaseHas('tenant_audit_logs', ['tenant_id' => $tenant->id, 'action' => 'owner_invitation.accepted']);
    expect(DB::table('staff_invitations')->where('id', $invitationId)->value('token_hash'))->not->toBe($fragment['token']);
    expect(json_encode(PlatformAuditLog::all()->toArray()))->not->toContain($fragment['token'], 'Owner-password1!');
    Notification::assertSentOnDemand(OwnerInvitationNotification::class, fn ($notice, $channels, $notifiable) => $notifiable->routes['mail'] === 'owner@example.test' && str_starts_with($notice->acceptUrl, "http://new.yoga.test$path#token="));
    Notification::assertSentOnDemandTimes(OwnerInvitationNotification::class, 2);
    expect(Hash::check('Owner-password1!', DB::table('users')->where('tenant_id', $tenant->id)->value('password')))->toBeTrue();
    $response = $this->post('http://new.yoga.test/login', ['email' => 'owner@example.test', 'password' => 'Owner-password1!']);
    app(TenantContext::class)->setTenant($tenant);
    $response->assertRedirect('/account');
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
    $this->get('http://new.yoga.test/dashboard')->assertRedirect('http://new.yoga.test/settings/two-factor');
});

test('mail failure leaves a retryable invitation without logging transport secrets', function () {
    $admin = platformOperator($this);
    $tenant = createPlatformStudio($this);
    verifyPlatformStudio($this, $tenant);
    Log::spy();
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('private-smtp-secret'));
    $base = "http://platform.yoga.test/platform/tenants/{$tenant->id}";

    $this->post("$base/owner-invitation")->assertRedirect();
    $row = DB::table('staff_invitations')->where('tenant_id', $tenant->id)->first();
    expect($row->sent_at)->toBeNull();
    expect($row->delivery_failed_at)->not->toBeNull();
    $this->assertDatabaseHas('platform_audit_logs', ['action' => 'owner_invitation.delivery_failed', 'actor_platform_admin_id' => $admin->id]);
    expect(json_encode(PlatformAuditLog::all()->toArray()))->not->toContain('private-smtp-secret');
    Log::shouldNotHaveReceived('error');
    Notification::fake();
    $this->post("$base/owner-invitation")->assertRedirect();
    $this->assertDatabaseHas('staff_invitations', ['id' => $row->id, 'delivery_failed_at' => null]);
    Notification::assertSentOnDemand(OwnerInvitationNotification::class);
});

test('expired revoked and unsent invitations cannot be accepted', function (string $state) {
    $this->freezeTime();
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test']);
    $attributes = match ($state) {
        'expired' => ['expires_at' => now()->subSecond(), 'sent_at' => now()->subDays(8)],
        'revoked' => ['revoked_at' => now(), 'sent_at' => now()],
        default => ['sent_at' => null],
    };
    $invitation = StaffInvitation::factory()->for($tenant)->create([
        'role' => 'owner', 'invited_by_user_id' => null, 'invited_by_platform_admin_id' => PlatformAdmin::factory()->create()->id,
        'token_hash' => hash('sha256', str_repeat('a', 64)), ...$attributes,
    ]);

    $this->postJson("http://lotus.yoga.test/owner-invitations/{$invitation->id}", [
        'token' => str_repeat('a', 64), 'name' => 'Vlasnik', 'password' => 'Owner-password1!', 'password_confirmation' => 'Owner-password1!',
    ])->assertNotFound();
    $this->assertDatabaseMissing('users', ['role' => 'owner']);
})->with(['expired', 'revoked', 'unsent']);

test('owner invitation never promotes an existing account or overwrites its password', function () {
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test']);
    $user = User::factory()->for($tenant)->create(['email' => 'owner@example.test']);
    $invitation = StaffInvitation::factory()->for($tenant)->create(['email' => $user->email, 'sent_at' => now(), 'token_hash' => hash('sha256', str_repeat('a', 64))]);

    $this->postJson("http://lotus.yoga.test/owner-invitations/{$invitation->id}", [
        'token' => str_repeat('a', 64), 'name' => 'Vlasnik', 'password' => 'Owner-password1!', 'password_confirmation' => 'Owner-password1!',
    ])->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'customer', 'password' => $user->password]);
    $this->assertDatabaseHas('staff_invitations', ['id' => $invitation->id, 'accepted_at' => null]);
});

test('owner invitation throttle is isolated by studio and rejects repeated attempts', function () {
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test']);
    $invitation = StaffInvitation::factory()->for($tenant)->create(['sent_at' => now()]);
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    TenantDomain::factory()->verified()->for($other)->create(['hostname' => 'balance.yoga.test']);
    $otherInvitation = StaffInvitation::factory()->for($other)->create(['sent_at' => now()]);

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson("http://lotus.yoga.test/owner-invitations/{$invitation->id}", [
            'token' => str_repeat('x', 64), 'name' => 'Vlasnik', 'password' => 'Owner-password1!', 'password_confirmation' => 'Owner-password1!',
        ])->assertUnprocessable();
    }
    $this->get("http://lotus.yoga.test/owner-invitations/{$invitation->id}")->assertTooManyRequests();
    $this->get("http://balance.yoga.test/owner-invitations/{$otherInvitation->id}")->assertOk();
});

test('owner notification escapes studio names and includes the trusted acceptance link', function () {
    $notice = new OwnerInvitationNotification('<script>alert("name")</script>', 'http://lotus.yoga.test/owner-invitations/1#token=example');
    $html = $notice->toMail(new AnonymousNotifiable)->render()->toHtml();

    expect($html)->toContain('&lt;script&gt;', 'http://lotus.yoga.test/owner-invitations/1#token=example')
        ->not->toContain('<script>alert');
});

test('initial owner email reaches the local inbox with a trusted domain and no token query string', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 11025, 'mail.mailers.smtp.url' => null,
        'mail.mailers.smtp.scheme' => 'smtp', 'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null]);
    platformOperator($this);
    $email = 'm1-07.'.Str::uuid().'@example.test';
    $this->post('http://platform.yoga.test/platform/tenants', [...platformStudioData(), 'owner_email' => $email])->assertRedirect();
    $tenant = Tenant::query()->sole();
    verifyPlatformStudio($this, $tenant);

    $this->post("http://platform.yoga.test/platform/tenants/{$tenant->id}/owner-invitation")->assertRedirect();
    $messages = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/search', ['query' => 'to:'.$email])->throw()->json('messages');
    expect($messages)->toHaveCount(1);
    $body = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/message/'.$messages[0]['ID'])->throw()->json('Text');
    expect(str_contains($body, 'http://new.yoga.test/owner-invitations/'))->toBeTrue();
    expect(str_contains($body, '#token='))->toBeTrue();
    expect(str_contains($body, '?token='))->toBeFalse();
})->group('local-mail');
