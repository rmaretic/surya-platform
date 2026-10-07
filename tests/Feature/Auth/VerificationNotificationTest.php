<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('sends verification notification', function () {
    $this->prepareStudio();
    Notification::fake();

    $user = User::factory()->for($this->studio)->unverified()->create();

    $this->signInToStudio($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('does not send verification notification if email is verified', function () {
    $this->prepareStudio();
    Notification::fake();

    $user = User::factory()->for($this->studio)->create();

    $this->signInToStudio($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('account', absolute: false));

    Notification::assertNothingSent();
});
