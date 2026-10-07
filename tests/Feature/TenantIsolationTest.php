<?php

use App\Actions\ListPlatformTenants;
use App\Actions\ReadTenantProfile;
use App\Actions\UpdateTenantProfile;
use App\Http\Middleware\RequireTenantAuthenticationReady;
use App\Models\PlatformAdmin;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\User;
use App\TenantCache;
use App\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

function createIsolationStudios(TestCase $test): void
{
    $test->lotus = Tenant::factory()->active()->create();
    $test->balance = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($test->lotus);
    $test->owner = User::factory()->for($test->lotus)->withTwoFactor()->create(['role' => 'owner']);
    $test->lotusProfile = TenantProfile::factory()->for($test->lotus)->create(['name' => 'Lotus']);
    TenantDomain::factory()->verified()->for($test->lotus)->create(['hostname' => 'lotus.yoga.test']);
    app(TenantContext::class)->setTenant($test->balance);
    $test->balanceProfile = TenantProfile::factory()->for($test->balance)->create(['name' => 'Balance']);
    app(TenantContext::class)->clear();

}

beforeEach(function (): void {
    $this->withoutMiddleware(RequireTenantAuthenticationReady::class);
    Route::get('/_test/profiles/{profile}', function (TenantProfile $profile, Request $request, ReadTenantProfile $action) {
        return $action->handle($request->user(), $profile);
    })->middleware(['web', 'auth']);
    Route::patch('/_test/profiles/{profile}', function (TenantProfile $profile, Request $request, UpdateTenantProfile $action) {
        return $action->handle($request->user(), $profile, $request->all());
    })->middleware(['web', 'auth']);
});

test('tenant queries fail closed without context including on the platform', function (string $model) {
    createIsolationStudios($this);
    expect(fn () => $model::query()->get())->toThrow(LogicException::class);
    expect(fn () => $model::query()->delete())->toThrow(LogicException::class);
    app(TenantContext::class)->setPlatform();
    expect(fn () => $model::query()->count())->toThrow(LogicException::class);
})->with([User::class, TenantDomain::class, TenantProfile::class, StaffInvitation::class, TenantAuditLog::class]);

test('scoped reads bulk writes deletes and manipulated relationships cannot reach another studio', function () {
    createIsolationStudios($this);
    app(TenantContext::class)->setTenant($this->lotus);
    expect(TenantProfile::query()->pluck('id')->all())->toBe([$this->lotusProfile->id]);
    expect(TenantProfile::query()->find($this->balanceProfile->id))->toBeNull();
    expect($this->balance->profile()->first())->toBeNull();
    expect(TenantProfile::query()->whereKey($this->balanceProfile->id)->update(['name' => 'Attack']))->toBe(0);
    expect(TenantProfile::query()->whereKey($this->balanceProfile->id)->delete())->toBe(0);
    expect(fn () => $this->balanceProfile->update(['name' => 'Attack']))->toThrow(AuthorizationException::class);
    expect(fn () => $this->balanceProfile->delete())->toThrow(AuthorizationException::class);
    expect(fn () => $this->balance->users()->create(['name' => 'Attack', 'email' => 'attack@example.test', 'password' => 'password']))
        ->toThrow(AuthorizationException::class);
    $this->assertDatabaseHas('tenant_profiles', ['id' => $this->balanceProfile->id, 'name' => 'Balance']);
});

test('refreshing or restoring a saved tenant model cannot bypass the current context', function () {
    createIsolationStudios($this);
    expect(fn () => $this->lotusProfile->fresh())->toThrow(LogicException::class);
    expect(fn () => $this->lotusProfile->refresh())->toThrow(LogicException::class);
    expect(fn () => $this->lotusProfile->newQueryForRestoration($this->lotusProfile->id)->first())->toThrow(LogicException::class);
    app(TenantContext::class)->setTenant($this->balance);
    expect($this->lotusProfile->fresh())->toBeNull();
    expect(fn () => $this->lotusProfile->refresh())->toThrow(ModelNotFoundException::class);
    expect($this->lotusProfile->newQueryForRestoration($this->lotusProfile->id)->first())->toBeNull();
    app(TenantContext::class)->setTenant($this->lotus);
    expect($this->lotusProfile->refresh()->name)->toBe('Lotus');
});

test('normal creates assign the server tenant and reject forced ownership changes', function () {
    createIsolationStudios($this);
    app(TenantContext::class)->setTenant($this->lotus);
    $user = User::query()->create(['name' => 'Member', 'email' => 'member@example.test', 'password' => 'password', 'tenant_id' => $this->balance->id]);
    expect($user->tenant_id)->toBe($this->lotus->id);
    expect(fn () => $user->forceFill(['tenant_id' => $this->balance->id])->save())->toThrow(AuthorizationException::class);
    expect(fn () => User::query()->whereKey($user->id)->update(['tenant_id' => $this->balance->id]))->toThrow(LogicException::class);
    app(TenantContext::class)->clear();
    expect(fn () => User::query()->create(['name' => 'No context']))->toThrow(LogicException::class);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'tenant_id' => $this->lotus->id]);
});

test('known foreign profile IDs return 404 for both reading and writing', function () {
    createIsolationStudios($this);
    $this->signInToStudio($this->owner);
    $url = 'http://lotus.yoga.test/_test/profiles/'.$this->balanceProfile->id;
    $this->getJson($url)->assertNotFound();
    $this->patchJson($url, ['name' => 'Attack', 'tenant_id' => $this->balance->id])->assertNotFound();
    $this->assertDatabaseHas('tenant_profiles', ['id' => $this->balanceProfile->id, 'name' => 'Balance']);
});

test('an owner can read and change their profile while client ownership fields are ignored', function () {
    createIsolationStudios($this);
    $url = 'http://lotus.yoga.test/_test/profiles/'.$this->lotusProfile->id;
    $this->signInToStudio($this->owner)->getJson($url)->assertOk()->assertJsonPath('name', 'Lotus');
    $this->patchJson($url, ['name' => 'New Lotus', 'tenant_id' => $this->balance->id])->assertOk();
    $this->assertDatabaseHas('tenant_profiles', ['id' => $this->lotusProfile->id, 'name' => 'New Lotus', 'tenant_id' => $this->lotus->id]);
});

test('a profile action enforces authorization for an insufficient role and guests', function () {
    createIsolationStudios($this);
    $url = 'http://lotus.yoga.test/_test/profiles/'.$this->lotusProfile->id;
    $this->patchJson($url, ['name' => 'Attack'])->assertUnauthorized();
    DB::table('users')->where('id', $this->owner->id)->update(['role' => 'manager']);
    $this->signInToStudio($this->owner)->patchJson($url, ['name' => 'Attack'])->assertForbidden();
    $this->assertDatabaseHas('tenant_profiles', ['id' => $this->lotusProfile->id, 'name' => 'Lotus']);
});

test('profile policies enforce role activity identity and current tenant independently of binding', function (string $role, bool $active, bool $allowed) {
    createIsolationStudios($this);
    app(TenantContext::class)->setTenant($this->lotus);
    $this->owner->role = $role;
    $this->owner->is_active = $active;
    foreach (['view', 'update'] as $ability) {
        expect(Gate::forUser($this->owner)->allows($ability, $this->lotusProfile))->toBe($allowed);
        expect(Gate::forUser($this->owner)->inspect($ability, $this->balanceProfile)->status())->toBe(404);
    }
})->with([
    ['owner', true, true], ['owner', false, false], ['manager', true, false],
    ['instructor', true, false], ['customer', true, false],
]);

test('an authorized action still rejects a foreign hydrated model and platform identity', function () {
    createIsolationStudios($this);
    app(TenantContext::class)->setTenant($this->lotus);
    expect(fn () => app(UpdateTenantProfile::class)->handle($this->owner, $this->balanceProfile, ['name' => 'Attack']))
        ->toThrow(AuthorizationException::class);
    $admin = PlatformAdmin::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    expect(Gate::forUser($admin)->allows('update', $this->lotusProfile))->toBeFalse();
    app(TenantContext::class)->setTenant($this->balance);
    expect(Gate::forUser($this->owner)->allows('update', $this->balanceProfile))->toBeFalse();
    app(TenantContext::class)->clear();
    expect(Gate::forUser($this->owner)->allows('update', $this->lotusProfile))->toBeFalse();
});

test('only an active platform admin in platform context may list all studios', function () {
    createIsolationStudios($this);
    $admin = PlatformAdmin::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    $action = app(ListPlatformTenants::class);
    expect(fn () => $action->handle($admin))->toThrow(AuthorizationException::class);
    app(TenantContext::class)->setTenant($this->lotus);
    expect(fn () => $action->handle($admin))->toThrow(AuthorizationException::class);
    app(TenantContext::class)->setPlatform();
    expect(fn () => $action->handle($this->owner))->toThrow(AuthorizationException::class);
    expect($action->handle($admin)->pluck('id')->all())->toBe([$this->lotus->id, $this->balance->id]);
    $admin->is_active = false;
    expect(fn () => $action->handle($admin))->toThrow(AuthorizationException::class);
});

test('cached values and invalidation are isolated by tenant and data type on the database store', function () {
    createIsolationStudios($this);
    config(['cache.default' => 'database']);
    $cache = app(TenantCache::class);
    app(TenantContext::class)->setTenant($this->lotus);
    expect($cache->remember('profile', 'same', 60, fn () => 'Lotus'))->toBe('Lotus');
    expect($cache->remember('staff', 'same', 60, fn () => 'Staff'))->toBe('Staff');
    app(TenantContext::class)->setTenant($this->balance);
    expect($cache->remember('profile', 'same', 60, fn () => 'Balance'))->toBe('Balance');
    $cache->forget('profile', 'same');
    app(TenantContext::class)->setTenant($this->lotus);
    expect($cache->remember('profile', 'same', 60, fn () => 'Wrong'))->toBe('Lotus');
    expect($cache->remember('staff', 'same', 60, fn () => 'Wrong'))->toBe('Staff');
    app(TenantContext::class)->clear();
    expect(fn () => $cache->remember('profile', 'same', 60, fn () => 'Leak'))->toThrow(LogicException::class);
    expect(fn () => $cache->forget('profile', 'same'))->toThrow(LogicException::class);
});

test('profile updates validate allowed fields before persisting', function (array $attributes, string $field) {
    createIsolationStudios($this);
    $this->signInToStudio($this->owner)->patchJson('http://lotus.yoga.test/_test/profiles/'.$this->lotusProfile->id, $attributes)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseHas('tenant_profiles', ['id' => $this->lotusProfile->id, 'name' => 'Lotus']);
})->with([
    [['name' => ''], 'name'], [['name' => str_repeat('x', 256)], 'name'],
    [['short_description' => str_repeat('x', 5001)], 'short_description'],
    [['email' => 'invalid'], 'email'], [['phone' => str_repeat('x', 51)], 'phone'],
    [['address' => str_repeat('x', 256)], 'address'],
]);
