<?php

use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Hash;

test('the admin command creates a separate unverified identity using a hidden password prompt', function () {
    $this->artisan('platform:create-admin', ['email' => 'ADMIN@example.test', '--name' => 'Platform operator'])
        ->expectsQuestion('Password', 'Test-password123!')
        ->expectsQuestion('Confirm password', 'Test-password123!')
        ->expectsOutput('Platform administrator created. Sign in and request email verification.')
        ->assertSuccessful();
    $admin = PlatformAdmin::query()->sole();
    expect($admin->email)->toBe('admin@example.test');
    expect($admin->email_verified_at)->toBeNull();
    expect(Hash::check('Test-password123!', $admin->password))->toBeTrue();
    $this->assertDatabaseCount('users', 0);
});

test('the admin command rejects duplicate identities and weak passwords without changing accounts', function () {
    $admin = PlatformAdmin::factory()->create(['email' => 'admin@example.test']);
    $this->artisan('platform:create-admin', ['email' => 'ADMIN@example.test', '--name' => 'Duplicate'])
        ->expectsQuestion('Password', 'short')
        ->expectsQuestion('Confirm password', 'short')
        ->assertFailed();
    $this->assertDatabaseCount('platform_admins', 1);
    expect($admin->fresh()->name)->toBe($admin->name);
});

test('the admin command refuses noninteractive credential creation', function () {
    $this->artisan('platform:create-admin', ['email' => 'admin@example.test', '--name' => 'Operator', '--no-interaction' => true])
        ->expectsOutput('Use an interactive terminal; passwords are never accepted as command arguments.')
        ->assertFailed();
    $this->assertDatabaseCount('platform_admins', 0);
});
