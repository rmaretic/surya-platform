<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('tenant authentication is available while unfinished administration remains closed', function (string $method, string $path, int $status) {
    Notification::fake();
    TenantDomain::factory()->verified()->for(Tenant::factory()->active())
        ->createQuietly(['hostname' => 'lotus.yoga.test']);

    $this->call($method, 'http://lotus.yoga.test'.$path, ['tenant_id' => 123, 'email' => 'same@example.test'], [], [], ['HTTP_ACCEPT' => 'application/json'])
        ->assertStatus($status);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    Notification::assertNothingSent();
})->with([
    ['GET', '/login', 200],
    ['POST', '/login', 422],
    ['GET', '/register', 200],
    ['POST', '/register', 422],
    ['POST', '/forgot-password', 200],
    ['POST', '/reset-password', 422],
    ['POST', '/two-factor-challenge', 302],
    ['GET', '/dashboard', 401],
    ['PATCH', '/settings/profile', 503],
    ['POST', '/user/two-factor-authentication', 401],
]);

test('an existing scaffold session cannot bypass the temporary boundary', function () {
    $user = User::factory()->for(Tenant::factory()->active())->createQuietly();
    TenantDomain::factory()->verified()->for($user->tenant)->createQuietly(['hostname' => 'lotus.yoga.test']);

    $this->actingAs($user)->get('http://lotus.yoga.test/settings/profile')->assertServiceUnavailable();
});

test('the neutral public page never exposes a user from a scaffold session', function () {
    $user = User::factory()->createQuietly();

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('auth.user', null));
});
