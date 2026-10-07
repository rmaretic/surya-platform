<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $this->prepareStudio();
    $user = User::factory()->for($this->studio)->create();

    $response = $this->signInToStudio($user)->get(route('password.confirm'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/ConfirmPassword'),
    );
});

test('password confirmation requires authentication', function () {
    $this->prepareStudio();
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});
