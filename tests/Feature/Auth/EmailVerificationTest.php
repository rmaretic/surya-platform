<?php

use App\Models\User;
use App\TenantContext;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->unverified()->create();

    $response = $this->signInToStudio($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('unverified users are redirected to the email verification prompt', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->unverified()->create();

    $response = $this->signInToStudio($user)->get(route('account'));

    $response->assertRedirect(route('verification.notice'));
});

test('email can be verified', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->signInToStudio($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    app(TenantContext::class)->setTenant($this->studio);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('account', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->signInToStudio($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    app(TenantContext::class)->setTenant($this->studio);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)],
    );

    $this->signInToStudio($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    app(TenantContext::class)->setTenant($this->studio);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to dashboard from verification prompt', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->create();

    Event::fake();

    $response = $this->signInToStudio($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('account', absolute: false));
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->signInToStudio($user)->get($verificationUrl)
        ->assertRedirect(route('account', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    app(TenantContext::class)->setTenant($this->studio);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
