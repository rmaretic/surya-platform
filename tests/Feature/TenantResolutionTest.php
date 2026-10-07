<?php

use App\Http\Middleware\RequireTenantAuthenticationReady;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->withoutMiddleware(RequireTenantAuthenticationReady::class);

    $contextResponse = fn (TenantContext $context) => response()->json([
        'tenant_id' => $context->tenant()?->id,
        'platform' => $context->isPlatform(),
    ]);

    Route::get('/_test/context', $contextResponse)->middleware('web');
    Route::post('/_test/context', $contextResponse)->middleware('web');
    Route::get('/platform/_test/context', $contextResponse)->middleware('web');
});

test('each verified studio hostname resolves its own tenant and clears the request context', function () {
    $lotus = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);
    $balance = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'balance.yoga.test']);

    $this->get('http://LOTUS.YOGA.TEST.:8000/_test/context')
        ->assertExactJson(['tenant_id' => $lotus->tenant_id, 'platform' => false]);
    expect(app(TenantContext::class)->tenant())->toBeNull();

    $this->get('http://balance.yoga.test/_test/context')
        ->assertExactJson(['tenant_id' => $balance->tenant_id, 'platform' => false]);
    expect(app(TenantContext::class)->tenant())->toBeNull();
});

test('the platform hostname never acquires an implicit tenant even if registered as a studio domain', function () {
    TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'platform.yoga.test']);

    $this->get('http://platform.yoga.test/platform/_test/context')
        ->assertExactJson(['tenant_id' => null, 'platform' => true]);

    expect(app(TenantContext::class)->isPlatform())->toBeFalse();
    expect(fn () => app(TenantContext::class)->requireTenant())->toThrow(LogicException::class);
});

test('unknown hosts return a neutral 404 without falling back to an existing studio', function (string $hostname) {
    TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);

    $this->get('http://'.$hostname.'/')->assertNotFound()->assertContent('Not found.');
})->with(['unknown.yoga.test', 'localhost', '127.0.0.1']);

test('unverified or inactive domains return a neutral 404', function (array $state) {
    TenantDomain::factory()->verified()->for(Tenant::factory()->active())
        ->createQuietly(['hostname' => 'lotus.yoga.test', ...$state]);

    $this->get('http://lotus.yoga.test/')->assertNotFound()->assertContent('Not found.');
})->with([
    'pending verification' => [['verification_status' => 'pending']],
    'missing verification timestamp' => [['verified_at' => null]],
    'inactive domain' => [['is_active' => false]],
]);

test('inactive studios return 503 without business data', function (string $status) {
    TenantDomain::factory()->verified()->for(Tenant::factory()->state(['status' => $status]))
        ->createQuietly(['hostname' => 'lotus.yoga.test']);

    $this->get('http://lotus.yoga.test/')->assertServiceUnavailable()
        ->assertContent('Service unavailable.');
})->with(['pending', 'suspended']);

test('client tenant identifiers cannot override the verified hostname', function () {
    $domain = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);
    $other = Tenant::factory()->active()->createQuietly();

    $this->post('http://lotus.yoga.test/_test/context?tenant_id='.$other->id, ['tenant_id' => $other->id], [
        'X-Tenant-ID' => (string) $other->id,
    ])->assertExactJson(['tenant_id' => $domain->tenant_id, 'platform' => false]);
});

test('forwarded host headers from an untrusted peer cannot change the studio', function () {
    $domain = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get('http://lotus.yoga.test/_test/context', [
            'X-Forwarded-Host' => 'platform.yoga.test',
            'Forwarded' => 'host=platform.yoga.test;proto=https',
        ])->assertExactJson(['tenant_id' => $domain->tenant_id, 'platform' => false]);
});

test('an unknown direct host cannot become trusted through forwarded headers', function (string $hostname) {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get('http://'.$hostname.'/platform/_test/context', [
            'X-Forwarded-Host' => 'platform.yoga.test',
        ])->assertNotFound();
})->with(['unknown.test', 'unknown.on-forge.com', 'unknown.on-vapor.com']);

test('only explicitly configured proxy addresses may forward the hostname', function () {
    config(['tenancy.trusted_proxies' => ['192.0.2.10']]);
    $domain = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->get('http://internal.test/_test/context', ['X-Forwarded-Host' => 'lotus.yoga.test'])
        ->assertExactJson(['tenant_id' => $domain->tenant_id, 'platform' => false]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
        ->get('http://internal.test/_test/context', ['X-Forwarded-Host' => 'lotus.yoga.test'])
        ->assertNotFound();
});

test('studio and platform routes are inaccessible through the other domain', function () {
    TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);

    $this->get('http://lotus.yoga.test/platform/_test/context')->assertNotFound();
    $this->get('http://platform.yoga.test/_test/context')->assertNotFound();
    $this->get('http://platform.yoga.test/login')->assertNotFound();
    $this->get('http://platform.yoga.test/settings/profile')->assertNotFound();
});

test('a revoked domain is rejected on the next request without cached tenant state', function () {
    $domain = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);
    $this->get('http://lotus.yoga.test/_test/context')->assertOk();

    app(TenantContext::class)->setTenant($domain->tenant);
    $domain->forceFill(['is_active' => false])->save();
    app(TenantContext::class)->clear();

    $this->get('http://lotus.yoga.test/_test/context')->assertNotFound();
    expect(app(TenantContext::class)->tenant())->toBeNull();
});

test('tenant context is available before route model binding', function () {
    $domain = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);
    Route::bind('tenantProbe', function (string $value): Tenant {
        $tenant = app(TenantContext::class)->requireTenant();
        abort_unless((string) $tenant->id === $value, 404);

        return $tenant;
    });
    Route::get('/_test/binding/{tenantProbe}', fn (Tenant $tenantProbe) => response()->json(['id' => $tenantProbe->id]))
        ->middleware('web');

    $this->get('http://lotus.yoga.test/_test/binding/'.$domain->tenant_id)
        ->assertExactJson(['id' => $domain->tenant_id]);
});

test('tenant context is available before the authentication provider runs', function () {
    $domain = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);
    $observedTenant = null;
    Auth::viaRequest('tenant-probe', function (Request $request) use (&$observedTenant) {
        $observedTenant = app(TenantContext::class)->requireTenant()->id;

        return null;
    });
    config(['auth.guards.tenant-probe' => ['driver' => 'tenant-probe']]);
    Route::get('/_test/auth', fn () => response('private'))->middleware(['web', 'auth:tenant-probe']);

    $this->get('http://lotus.yoga.test/_test/auth')->assertRedirect();

    expect($observedTenant)->toBe($domain->tenant_id);
});

test('context is cleared after a downstream exception before the next request', function () {
    TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'lotus.yoga.test']);
    Route::get('/_test/failure', function (): never {
        app(TenantContext::class)->requireTenant();
        throw new RuntimeException('Test failure');
    })->middleware('web');

    $this->get('http://lotus.yoga.test/_test/failure')->assertStatus(500);
    expect(app(TenantContext::class)->tenant())->toBeNull();

    $this->get('http://platform.yoga.test/platform/_test/context')
        ->assertExactJson(['tenant_id' => null, 'platform' => true]);
});

test('IDN domains are stored and resolved through the same canonical hostname', function () {
    $domain = TenantDomain::factory()->verified()->for(Tenant::factory()->active())->createQuietly(['hostname' => 'münchen.example']);

    app(TenantContext::class)->setTenant($domain->tenant);
    expect($domain->fresh()->hostname)->toBe('xn--mnchen-3ya.example');
    $this->get('http://xn--mnchen-3ya.example/_test/context')
        ->assertExactJson(['tenant_id' => $domain->tenant_id, 'platform' => false]);
});

test('CORS preflight cannot bypass rejection of an unknown hostname', function () {
    config(['cors.paths' => ['api/*'], 'cors.allowed_origins' => ['*'], 'cors.allowed_methods' => ['*']]);

    $this->options('http://unknown.yoga.test/api/example', [], [
        'Origin' => 'http://platform.yoga.test',
        'Access-Control-Request-Method' => 'POST',
    ])->assertNotFound()->assertContent('Not found.');
});
