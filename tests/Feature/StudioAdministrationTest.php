<?php

use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;

function studioStaffOwner(): User
{
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test', 'is_primary' => true]);
    TenantProfile::factory()->for($tenant)->create();

    return User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
}

function staffAcceptanceData(string $token): array
{
    return ['token' => $token, 'name' => 'Novi instruktor', 'password' => 'Staff-password1!', 'password_confirmation' => 'Staff-password1!'];
}

test('owner publishes only the current studio profile and records a safe audit', function () {
    $owner = studioStaffOwner();
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    TenantDomain::factory()->verified()->for($other)->create(['hostname' => 'balance.yoga.test']);
    TenantProfile::factory()->for($other)->create(['phone' => 'Balance-phone']);
    $this->signInToStudio($owner);

    $this->patch('http://lotus.yoga.test/studio/profile', ['phone' => 'Lotus-phone', 'tenant_id' => $other->id, 'design_key' => 'injected', 'token' => 'private-secret'])->assertRedirect();
    $this->get('http://lotus.yoga.test/')->assertInertia(fn (Assert $page) => $page->where('profile.phone', 'Lotus-phone')->missing('profile.tenant_id')->where('auth.user', null));
    $this->get('http://balance.yoga.test/')->assertInertia(fn (Assert $page) => $page->where('profile.phone', 'Balance-phone'));
    $this->assertDatabaseHas('tenant_audit_logs', ['tenant_id' => $owner->tenant_id, 'actor_user_id' => $owner->id, 'action' => 'profile.updated']);
    expect(DB::table('tenant_audit_logs')->value('changes'))->not->toContain('private-secret', 'injected');
});

test('manager instructor and customer cannot manage profile staff or invitations', function (string $role) {
    $owner = studioStaffOwner();
    $user = User::factory()->for($owner->tenant)->create(['role' => $role]);
    $this->signInToStudio($user);
    Notification::fake();

    $this->get('http://lotus.yoga.test/studio/staff')->assertForbidden();
    $this->patchJson('http://lotus.yoga.test/studio/profile', ['phone' => 'forbidden'])->assertForbidden();
    $this->postJson('http://lotus.yoga.test/studio/staff/invitations', ['email' => 'new@example.test', 'role' => 'manager'])->assertForbidden();
    $this->patchJson("http://lotus.yoga.test/studio/staff/{$owner->id}", ['role' => 'manager'])->assertForbidden();
    $this->postJson("http://lotus.yoga.test/studio/staff/{$owner->id}/deactivate", ['confirmed' => true])->assertForbidden();
    $this->assertDatabaseMissing('tenant_profiles', ['phone' => 'forbidden']);
    Notification::assertNothingSent();
})->with(['manager', 'instructor', 'customer']);

test('each studio role gets its limited home and own account without secrets', function (string $role) {
    $owner = studioStaffOwner();
    $user = $role === 'owner' ? $owner : User::factory()->for($owner->tenant)->create(['role' => $role]);
    $this->signInToStudio($user);

    $this->get('http://lotus.yoga.test/account')->assertInertia(fn (Assert $page) => $page->component('studio/Account')->where('role', $role)->where('auth.user.id', $user->id)->missing('auth.user.password')->missing('auth.user.two_factor_secret'));
    if ($role === 'customer') {
        $this->get('http://lotus.yoga.test/portal')->assertOk();
        $this->get('http://lotus.yoga.test/dashboard')->assertForbidden();
    } else {
        $this->get('http://lotus.yoga.test/dashboard')->assertInertia(fn (Assert $page) => $page->component('studio/Dashboard')->where('role', $role));
        $this->get('http://lotus.yoga.test/portal')->assertForbidden();
    }
})->with(['owner', 'manager', 'instructor', 'customer']);

test('staff invitation rotates tokens binds email and tenant and is consumed once', function () {
    $this->freezeTime();
    $owner = studioStaffOwner();
    $this->signInToStudio($owner);
    Notification::fake();
    $this->post('http://lotus.yoga.test/studio/staff/invitations', ['email' => ' Staff@Example.test ', 'role' => 'instructor'])->assertRedirect();
    $invitation = DB::table('staff_invitations')->sole();
    $first = Notification::sent(new AnonymousNotifiable, StaffInvitationNotification::class)->first();
    parse_str(parse_url($first->acceptUrl, PHP_URL_FRAGMENT), $old);
    $this->post("http://lotus.yoga.test/studio/staff/invitations/{$invitation->id}/send")->assertRedirect();
    $notice = Notification::sent(new AnonymousNotifiable, StaffInvitationNotification::class)->last();
    parse_str(parse_url($notice->acceptUrl, PHP_URL_FRAGMENT), $fragment);
    $this->get('http://lotus.yoga.test/studio/staff')->assertInertia(fn (Assert $page) => $page->component('studio/Staff')->missing('invitations.data.0.token_hash')->missing('staff.data.0.password'));
    $this->post('http://lotus.yoga.test/logout');
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    TenantDomain::factory()->verified()->for($other)->create(['hostname' => 'balance.yoga.test']);
    User::factory()->for($other)->create(['email' => 'staff@example.test']);
    $data = [...staffAcceptanceData($fragment['token']), 'email' => 'attacker@example.test', 'role' => 'owner', 'tenant_id' => $other->id];
    $path = "/staff-invitations/{$invitation->id}";

    $this->get('http://balance.yoga.test'.$path)->assertNotFound();
    $this->postJson('http://balance.yoga.test'.$path, $data)->assertNotFound();
    $this->get('http://lotus.yoga.test'.$path)->assertInertia(fn (Assert $page) => $page->component('auth/AcceptStaffInvitation')->missing('token')->missing('email'));
    $this->postJson('http://lotus.yoga.test'.$path, staffAcceptanceData($old['token']))->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->post('http://lotus.yoga.test'.$path, $data)->assertRedirect('http://lotus.yoga.test/login');
    $this->postJson('http://lotus.yoga.test'.$path, $data)->assertNotFound();
    $this->assertDatabaseHas('users', ['tenant_id' => $owner->tenant_id, 'email' => 'staff@example.test', 'role' => 'instructor', 'is_active' => true]);
    $this->assertDatabaseMissing('users', ['email' => 'attacker@example.test']);
    $this->assertDatabaseHas('tenant_audit_logs', ['tenant_id' => $owner->tenant_id, 'action' => 'staff_invitation.accepted']);
    expect(json_encode(DB::table('tenant_audit_logs')->get()))->not->toContain($fragment['token'], 'Staff-password1!');
    Notification::assertSentOnDemandTimes(StaffInvitationNotification::class, 2);
});

test('staff invitations reject invalid roles duplicate invitations and existing accounts', function () {
    $owner = studioStaffOwner();
    $this->signInToStudio($owner);
    Notification::fake();

    $this->postJson('http://lotus.yoga.test/studio/staff/invitations', ['email' => 'new@example.test', 'role' => 'owner'])->assertUnprocessable()->assertJsonValidationErrors('role');
    $this->postJson('http://lotus.yoga.test/studio/staff/invitations', ['email' => $owner->email, 'role' => 'manager'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->post('http://lotus.yoga.test/studio/staff/invitations', ['email' => 'new@example.test', 'role' => 'manager'])->assertRedirect();
    $this->postJson('http://lotus.yoga.test/studio/staff/invitations', ['email' => 'NEW@example.test', 'role' => 'manager'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertDatabaseCount('staff_invitations', 1);
    Notification::assertSentOnDemandTimes(StaffInvitationNotification::class, 1);
});

test('expired revoked and unsent staff invitations cannot be accepted', function (string $state) {
    $this->freezeTime();
    $owner = studioStaffOwner();
    $attributes = match ($state) {
        'expired' => ['expires_at' => now()->subSecond(), 'sent_at' => now()->subDays(8)],
        'revoked' => ['revoked_at' => now(), 'sent_at' => now()],
        default => ['sent_at' => null],
    };
    $invitation = StaffInvitation::factory()->for($owner->tenant)->create(['role' => 'manager', 'invited_by_user_id' => $owner->id, 'invited_by_platform_admin_id' => null, 'token_hash' => hash('sha256', str_repeat('a', 64)), ...$attributes]);

    $this->postJson("http://lotus.yoga.test/staff-invitations/{$invitation->id}", staffAcceptanceData(str_repeat('a', 64)))->assertNotFound();
    $this->assertDatabaseCount('users', 1);
})->with(['expired', 'revoked', 'unsent']);

test('owner changes staff roles but cannot modify owners customers or foreign staff', function () {
    $owner = studioStaffOwner();
    $staff = User::factory()->for($owner->tenant)->create(['role' => 'manager']);
    $customer = User::factory()->for($owner->tenant)->create();
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    $foreign = User::factory()->for($other)->create(['role' => 'manager']);
    $this->signInToStudio($owner);

    $this->patch("http://lotus.yoga.test/studio/staff/{$staff->id}", ['role' => 'instructor'])->assertRedirect();
    $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'instructor']);
    $this->assertDatabaseHas('tenant_audit_logs', ['actor_user_id' => $owner->id, 'action' => 'staff.role_changed', 'subject_id' => $staff->id]);
    foreach ([$owner, $customer] as $protected) {
        $this->patchJson("http://lotus.yoga.test/studio/staff/{$protected->id}", ['role' => 'manager'])->assertForbidden();
        $this->postJson("http://lotus.yoga.test/studio/staff/{$protected->id}/deactivate", ['confirmed' => true])->assertForbidden();
    }
    $this->patchJson("http://lotus.yoga.test/studio/staff/{$foreign->id}", ['role' => 'manager'])->assertNotFound();
    $this->postJson("http://lotus.yoga.test/studio/staff/{$foreign->id}/deactivate", ['confirmed' => true])->assertNotFound();
});

test('deactivation invalidates a real existing staff session and prevents login', function () {
    $owner = studioStaffOwner();
    $staff = User::factory()->for($owner->tenant)->create(['role' => 'manager']);
    $login = $this->post('http://lotus.yoga.test/login', ['email' => $staff->email, 'password' => 'password']);
    $cookie = $login->getCookie(config('session.cookie'))->getValue();
    $this->withCookie(config('session.cookie'), $cookie)->withCredentials();
    $this->get('http://lotus.yoga.test/dashboard')->assertOk();
    $this->withCookie(config('session.cookie'), 'separate-owner-browser');
    $this->signInToStudio($owner);
    $this->postJson("http://lotus.yoga.test/studio/staff/{$staff->id}/deactivate", [])->assertUnprocessable()->assertJsonValidationErrors('confirmed');

    $this->post("http://lotus.yoga.test/studio/staff/{$staff->id}/deactivate", ['confirmed' => true])->assertRedirect();
    $this->assertDatabaseHas('users', ['id' => $staff->id, 'is_active' => false, 'remember_token' => null]);
    $this->assertDatabaseMissing('tenant_sessions', ['tenant_id' => $owner->tenant_id, 'user_id' => $staff->id]);
    $this->withCookie(config('session.cookie'), $cookie);
    $this->get('http://lotus.yoga.test/dashboard')->assertRedirect('http://lotus.yoga.test/login');
    $this->postJson('http://lotus.yoga.test/login', ['email' => $staff->email, 'password' => 'password'])->assertUnprocessable();
});

test('failed staff mail can be retried and revoked without exposing transport secrets', function () {
    $owner = studioStaffOwner();
    $this->signInToStudio($owner);
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('smtp-private-secret'));
    $this->post('http://lotus.yoga.test/studio/staff/invitations', ['email' => 'new@example.test', 'role' => 'manager'])->assertRedirect();
    $invitation = DB::table('staff_invitations')->sole();
    expect($invitation->sent_at)->toBeNull();
    expect($invitation->delivery_failed_at)->not->toBeNull();
    expect(json_encode(DB::table('tenant_audit_logs')->get()))->not->toContain('smtp-private-secret');
    Notification::fake();

    $this->post("http://lotus.yoga.test/studio/staff/invitations/{$invitation->id}/send")->assertRedirect();
    $this->assertDatabaseHas('staff_invitations', ['id' => $invitation->id, 'delivery_failed_at' => null]);
    $this->post("http://lotus.yoga.test/studio/staff/invitations/{$invitation->id}/revoke")->assertRedirect();
    $this->post("http://lotus.yoga.test/studio/staff/invitations/{$invitation->id}/send")->assertNotFound();
    $this->assertDatabaseHas('tenant_audit_logs', ['action' => 'staff_invitation.revoked', 'actor_user_id' => $owner->id]);
    Notification::assertSentOnDemandTimes(StaffInvitationNotification::class, 1);
});

test('staff notification escapes studio names', function () {
    $notice = new StaffInvitationNotification('<script>alert("name")</script>', 'http://lotus.yoga.test/staff-invitations/1#token=example', 'manager');
    $html = $notice->toMail(new AnonymousNotifiable)->render()->toHtml();
    expect($html)->toContain('&lt;script&gt;')->not->toContain('<script>alert');
});

test('profile validation rejects invalid public values without publishing or auditing', function () {
    $owner = studioStaffOwner();
    $this->signInToStudio($owner);
    $before = DB::table('tenant_profiles')->where('tenant_id', $owner->tenant_id)->first();

    $this->patchJson('http://lotus.yoga.test/studio/profile', ['name' => '', 'email' => 'invalid', 'phone' => str_repeat('1', 51), 'address' => ['invalid'], 'short_description' => str_repeat('x', 5001)])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone', 'address', 'short_description']);
    expect(DB::table('tenant_profiles')->where('id', $before->id)->first())->toEqual($before);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
});

test('staff acceptance never promotes an account created after the invitation', function () {
    $owner = studioStaffOwner();
    $user = User::factory()->for($owner->tenant)->create(['email' => 'existing@example.test']);
    $invitation = StaffInvitation::factory()->for($owner->tenant)->create(['email' => $user->email, 'role' => 'manager', 'invited_by_user_id' => $owner->id, 'invited_by_platform_admin_id' => null, 'sent_at' => now(), 'token_hash' => hash('sha256', str_repeat('a', 64))]);

    $this->postJson("http://lotus.yoga.test/staff-invitations/{$invitation->id}", staffAcceptanceData(str_repeat('a', 64)))->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'customer', 'password' => $user->password]);
    $this->assertDatabaseHas('staff_invitations', ['id' => $invitation->id, 'accepted_at' => null]);
});

test('owner cannot resend or revoke another studio invitation or a platform owner invitation', function () {
    $owner = studioStaffOwner();
    $initial = StaffInvitation::factory()->for($owner->tenant)->create();
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    $otherInviter = User::factory()->for($other)->create(['role' => 'owner']);
    $foreign = StaffInvitation::factory()->for($other)->create(['role' => 'manager', 'invited_by_user_id' => $otherInviter->id, 'invited_by_platform_admin_id' => null]);
    $this->signInToStudio($owner);
    Notification::fake();

    foreach ([$initial, $foreign] as $invitation) {
        $this->post("http://lotus.yoga.test/studio/staff/invitations/{$invitation->id}/send")->assertNotFound();
        $this->post("http://lotus.yoga.test/studio/staff/invitations/{$invitation->id}/revoke")->assertNotFound();
        $this->assertDatabaseHas('staff_invitations', ['id' => $invitation->id, 'revoked_at' => null]);
    }
    Notification::assertNothingSent();
});

test('studio management requires login and completed owner enrolment', function () {
    $owner = studioStaffOwner();
    DB::table('users')->where('id', $owner->id)->update(['two_factor_confirmed_at' => null, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null]);
    $owner->refresh();
    $this->get('http://lotus.yoga.test/studio/profile')->assertRedirect('http://lotus.yoga.test/login');
    $this->postJson('http://lotus.yoga.test/studio/staff/invitations', [])->assertUnauthorized();
    $this->signInToStudio($owner);

    $this->get('http://lotus.yoga.test/studio/staff')->assertRedirect('http://lotus.yoga.test/settings/two-factor');
    $this->patch('http://lotus.yoga.test/studio/profile', ['phone' => 'forbidden'])->assertRedirect('http://lotus.yoga.test/settings/two-factor');
    $this->assertDatabaseMissing('tenant_profiles', ['phone' => 'forbidden']);
});
