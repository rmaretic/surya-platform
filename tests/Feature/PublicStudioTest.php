<?php

use App\Actions\ManagePlatformTenant;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\User;
use App\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

function publicStudio(string $hostname, string $design): Tenant
{
    $tenant = Tenant::factory()->active()->create(['design_key' => $design]);
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => $hostname, 'is_primary' => true]);
    TenantProfile::factory()->for($tenant)->create(['name' => 'Javni '.$design, 'phone' => $design.'-phone']);

    return $tenant;
}

test('registered designs resolve both public pages with only the public profile contract', function (string $design, string $path, string $component) {
    publicStudio('lotus.yoga.test', $design);

    $this->get('http://lotus.yoga.test'.$path)->assertInertia(fn (Assert $page) => $page
        ->component('sites/'.$design.'/'.$component)
        ->where('profile.name', 'Javni '.$design)->where('profile.phone', $design.'-phone')
        ->has('profile', 5)->where('auth.user', null)->missing('tenant')->missing('studioName')->missing('studioAccess')
        ->missing('profile.id')->missing('profile.tenant_id')->missing('profile.created_at'));
})->with([
    ['lotus', '/', 'Home'], ['lotus', '/about', 'About'],
    ['balance', '/', 'Home'], ['balance', '/about', 'About'],
]);

test('changing a registered design switches pages without changing the studios public data', function () {
    $tenant = publicStudio('lotus.yoga.test', 'lotus');
    $this->get('http://lotus.yoga.test/')->assertInertia(fn (Assert $page) => $page->component('sites/lotus/Home'));
    $admin = PlatformAdmin::factory()->create(['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()]);
    app(TenantContext::class)->setPlatform();

    app(ManagePlatformTenant::class)->changeDesign($admin, $tenant, 'balance');

    $this->get('http://lotus.yoga.test/')->assertInertia(fn (Assert $page) => $page->component('sites/balance/Home')->where('profile.name', 'Javni lotus')->where('profile.phone', 'lotus-phone'));
    $this->get('http://lotus.yoga.test/about')->assertInertia(fn (Assert $page) => $page->component('sites/balance/About')->where('profile.name', 'Javni lotus'));
    $this->assertDatabaseHas('platform_audit_logs', ['action' => 'tenant.design_changed', 'target_tenant_id' => $tenant->id]);
});

test('unregistered stored designs fail with 503 instead of falling back or exposing their path', function (string $design) {
    $tenant = publicStudio('lotus.yoga.test', $design);
    Log::spy();

    foreach (['/', '/about'] as $path) {
        $this->get('http://lotus.yoga.test'.$path)->assertServiceUnavailable()->assertDontSee('Javni '.$design)->assertDontSee($design);
    }
    Log::shouldHaveReceived('warning')->with('Public studio design is not registered.', ['tenant_id' => $tenant->id])->twice();
})->with(['../../PlatformAdmin', 'unregistered-design']);

test('design mutation rejects component paths at the registry boundary', function () {
    $tenant = publicStudio('lotus.yoga.test', 'lotus');
    $admin = PlatformAdmin::factory()->create(['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()]);
    app(TenantContext::class)->setPlatform();

    expect(fn () => app(ManagePlatformTenant::class)->changeDesign($admin, $tenant, '../../PlatformAdmin'))->toThrow(ValidationException::class);
    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'design_key' => 'lotus']);
    $this->assertDatabaseCount('platform_audit_logs', 0);
});

test('sequential public requests do not mix designs or profiles and ignore client tenant inputs', function () {
    $lotus = publicStudio('lotus.yoga.test', 'lotus');
    $balance = publicStudio('balance.yoga.test', 'balance');

    $this->get('http://lotus.yoga.test/?tenant_id='.$balance->id.'&design_key=balance')->assertInertia(fn (Assert $page) => $page->component('sites/lotus/Home')->where('profile.phone', 'lotus-phone'));
    $this->get('http://balance.yoga.test/about?tenant_id='.$lotus->id)->assertInertia(fn (Assert $page) => $page->component('sites/balance/About')->where('profile.phone', 'balance-phone'));
    $this->get('http://lotus.yoga.test/about')->assertInertia(fn (Assert $page) => $page->component('sites/lotus/About')->where('profile.phone', 'lotus-phone'));
    expect(app(TenantContext::class)->tenant())->toBeNull();
});

test('public pages omit authenticated identities validation errors and private flash data', function (string $path) {
    $tenant = publicStudio('lotus.yoga.test', 'lotus');
    $user = User::factory()->for($tenant)->create(['name' => 'Private staff identity', 'email' => 'private-staff@example.test', 'role' => 'manager']);
    $this->signInToStudio($user);
    app(TenantContext::class)->setTenant($tenant);
    $this->withSession([
        'errors' => (new ViewErrorBag)->put('default', new MessageBag(['email' => 'private-staff@example.test'])),
        SessionKey::FLASH_DATA => ['private' => 'private-session-secret'],
    ]);

    $response = $this->get('http://lotus.yoga.test'.$path);

    $response->assertInertia(fn (Assert $page) => $page->where('auth.user', null)->has('errors', 0)->missing('studioAccess')->missing('sidebarOpen'));
    $response->assertDontSee('Private staff identity')->assertDontSee('private-staff@example.test')->assertDontSee('private-session-secret');
})->with(['/', '/about']);

test('platform remains neutral and public about rejects platform unknown and pending domains', function () {
    publicStudio('lotus.yoga.test', 'lotus');
    TenantDomain::factory()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'pending.yoga.test']);

    $this->get('http://platform.yoga.test/')->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('profile', null));
    $this->get('http://platform.yoga.test/about')->assertNotFound();
    $this->get('http://unknown.yoga.test/about')->assertNotFound();
    $this->get('http://pending.yoga.test/about')->assertNotFound();
});

test('a studio without a profile exposes only its own name and empty contact fields', function () {
    $tenant = Tenant::factory()->active()->create(['name' => 'Novi studio', 'design_key' => 'balance']);
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'balance.yoga.test']);

    $this->get('http://balance.yoga.test/')->assertInertia(fn (Assert $page) => $page->component('sites/balance/Home')->where('profile', [
        'name' => 'Novi studio', 'short_description' => null, 'email' => null, 'phone' => null, 'address' => null,
    ]));
});
