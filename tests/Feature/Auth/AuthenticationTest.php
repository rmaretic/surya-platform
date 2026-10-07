<?php

use App\Models\User;
use App\TenantContext;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $this->prepareStudio();
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('account', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->prepareStudio();
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->for($this->studio)->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    app(TenantContext::class)->setTenant($this->studio);
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->create();

    $response = $this->signInToStudio($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->create();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
