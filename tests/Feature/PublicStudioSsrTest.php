<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\TenantContext;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Inertia\Ssr\SsrState;
use Inertia\Testing\AssertableInertia as Assert;

function seoStudio(string $hostname = 'lotus.yoga.test', string $design = 'lotus'): Tenant
{
    $tenant = Tenant::factory()->active()->create(['design_key' => $design]);
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => $hostname, 'is_primary' => true]);
    TenantProfile::factory()->for($tenant)->create([
        'name' => 'Studio '.$design,
        'short_description' => 'Javni opis studija '.$design.'.',
    ]);

    return $tenant;
}

test('public metadata uses the verified primary origin and ignores request headers query and port', function (string $path, string $title) {
    $tenant = seoStudio();
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'alias.yoga.test']);
    config(['tenancy.auth_scheme' => 'https', 'tenancy.auth_port' => 8443]);

    $this->withHeaders(['X-Forwarded-Host' => 'attacker.test', 'X-Forwarded-Proto' => 'http', 'X-Forwarded-Port' => '9999'])
        ->get('http://alias.yoga.test:9999'.$path.'?tenant_id=99&canonical=https://attacker.test')
        ->assertInertia(fn (Assert $page) => $page->where('seo', [
            'title' => $title,
            'description' => 'Javni opis studija lotus.',
            'canonical' => 'https://lotus.yoga.test:8443'.rtrim($path, '/'),
            'robots' => 'noindex, nofollow',
        ]));
})->with([
    'home' => ['/', 'Studio lotus'],
    'about' => ['/about', 'O studiju — Studio lotus'],
]);

test('indexing requires an explicit production opt in and never indexes demo hosts', function (string $environment, bool $enabled, string $hostname, string $robots) {
    seoStudio($hostname);
    $this->app->instance('env', $environment);
    config(['tenancy.public_indexing' => $enabled, 'tenancy.auth_port' => null]);

    $this->get('http://'.$hostname.'/')->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', $robots)
        ->where('seo.canonical', ($environment === 'production' ? 'https' : 'http').'://'.$hostname));
})->with([
    'local opt in still excluded' => ['local', true, 'studio.hr', 'noindex, nofollow'],
    'staging excluded' => ['staging', true, 'studio.hr', 'noindex, nofollow'],
    'production default excluded' => ['production', false, 'studio.hr', 'noindex, nofollow'],
    'production explicitly enabled' => ['production', true, 'studio.hr', 'index, follow'],
    'test domain excluded' => ['production', true, 'lotus.yoga.test', 'noindex, nofollow'],
]);

test('an alias stays noindex even when the primary production domain is indexable', function () {
    $tenant = seoStudio('studio.hr');
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'demo.yoga.test']);
    $this->app->instance('env', 'production');
    config(['tenancy.public_indexing' => true]);

    $this->get('http://demo.yoga.test/')->assertInertia(fn (Assert $page) => $page
        ->where('seo.robots', 'noindex, nofollow')->where('seo.canonical', 'https://studio.hr'));
});

test('an unusable primary domain never becomes canonical and the public alias stays noindex', function (array $attributes) {
    $tenant = seoStudio();
    TenantDomain::query()->where('is_primary', true)->firstOrFail()->forceFill($attributes)->save();
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'alias.yoga.test']);

    $this->get('http://alias.yoga.test/about')->assertInertia(fn (Assert $page) => $page
        ->where('seo.canonical', null)->where('seo.robots', 'noindex, nofollow'));
})->with([
    'not primary' => [['is_primary' => false]],
    'inactive' => [['is_active' => false]],
    'pending' => [['verification_status' => 'pending']],
    'missing verification time' => [['verified_at' => null]],
]);

test('missing public description gets a Croatian fallback and unknown hosts expose no metadata', function () {
    seoStudio();
    TenantProfile::query()->firstOrFail()->update(['short_description' => null]);

    $this->get('http://lotus.yoga.test/about')->assertInertia(fn (Assert $page) => $page
        ->where('seo.description', 'Upoznajte Studio lotus. Informacije o studiju i kontakt.'));
    $this->get('http://unknown.yoga.test/')->assertNotFound()->assertDontSee('Studio lotus')->assertDontSee('canonical');
});

test('SSR failure returns the escaped client shell and logs only the safe error category', function (string $failure) {
    seoStudio();
    TenantProfile::query()->firstOrFail()->update([
        'name' => 'Studio <script>alert(1)</script>',
        'short_description' => 'Opis "><script>alert(2)</script>',
    ]);
    Vite::useHotFile(storage_path('framework/ssr-test-unused.hot'));
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false, 'inertia.ssr.url' => 'http://127.0.0.1:13799']);
    Http::preventStrayRequests();
    Http::fake(['http://127.0.0.1:13799/render' => $failure === 'connection'
        ? Http::failedConnection('secret-connection-message')
        : Http::response(['type' => 'render', 'error' => 'secret-render-message', 'stack' => 'secret-stack'], 500)]);
    Log::spy();

    $response = $this->get('http://lotus.yoga.test/about?token=private-query-token');

    $response->assertOk()->assertSee('<div id="app"></div>', false)->assertSee('<html lang="hr"', false)
        ->assertSee('Studio &lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('Opis &quot;&gt;&lt;script&gt;alert(2)&lt;/script&gt;', false)
        ->assertSee('href="http://lotus.yoga.test/about"', false)
        ->assertSee('content="noindex, nofollow"', false)
        ->assertDontSee('<script>alert(', false)->assertDontSee('data-server-rendered', false)
        ->assertDontSee('secret-render-message')->assertDontSee('secret-connection-message');
    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->once()->with('SSR rendering failed; using client rendering.', ['type' => $failure]);
    Log::shouldNotHaveReceived('error');
})->with(['connection', 'render']);

test('one live SSR process renders Lotus Balance Lotus HTML without mixed content metadata or designs', function () {
    seoStudio();
    seoStudio('balance.yoga.test', 'balance');
    Vite::useHotFile(storage_path('framework/ssr-test-unused.hot'));
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.url' => getenv('SSR_TEST_URL'), 'inertia.ssr.throw_on_error' => true]);

    foreach (['/', '/about'] as $path) {
        foreach ([['lotus', 'balance'], ['balance', 'lotus'], ['lotus', 'balance']] as [$design, $other]) {
            /** Match Laravel's scoped request lifecycle while retaining the same Node process. */
            $this->app->forgetInstance(SsrState::class);
            $response = $this->get('http://'.$design.'.yoga.test'.$path);

            $response->assertOk()->assertSee('data-server-rendered="true"', false)
                ->assertSee('<html lang="hr"', false)
                ->assertSee('Studio '.$design)->assertSee('Javni opis studija '.$design.'.')
                ->assertSee('href="http://'.$design.'.yoga.test'.rtrim($path, '/').'"', false)
                ->assertSee('content="noindex, nofollow"', false)
                ->assertSee('class="'.$design.'-site', false)
                ->assertDontSee('Studio '.$other)->assertDontSee($other.'.yoga.test')->assertDontSee($other.'-site');
            $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
            expect($document->querySelector('title')->textContent)->toBe(($path === '/' ? '' : 'O studiju — ').'Studio '.$design);
            expect($document->querySelector('#app')->textContent)->toContain('Studio '.$design, 'Javni opis studija '.$design.'.');
            expect($document->querySelectorAll('title'))->toHaveCount(1);
            expect($document->querySelectorAll('meta[name="description"]'))->toHaveCount(1);
            expect($document->querySelectorAll('link[rel="canonical"]'))->toHaveCount(1);
        }
    }
})->skip(fn (): bool => ! getenv('SSR_TEST_URL'), 'Run the built SSR server and set SSR_TEST_URL to execute the real Node integration.');

test('live SSR escapes public text in title description and rendered content', function () {
    seoStudio();
    $name = 'Studio <script>alert(1)</script>';
    $description = 'Opis "><script>alert(2)</script>';
    TenantProfile::query()->firstOrFail()->update(['name' => $name, 'short_description' => $description]);
    Vite::useHotFile(storage_path('framework/ssr-test-unused.hot'));
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.url' => getenv('SSR_TEST_URL'), 'inertia.ssr.throw_on_error' => true]);

    $response = $this->get('http://lotus.yoga.test/');

    $response->assertSee('data-server-rendered="true"', false)->assertDontSee('<script>alert(', false);
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    expect($document->querySelector('title')->textContent)->toBe($name);
    expect($document->querySelector('meta[name="description"]')->getAttribute('content'))->toBe($description);
    expect($document->querySelector('#app')->textContent)->toContain($name, $description);
})->skip(fn (): bool => ! getenv('SSR_TEST_URL'), 'Run the built SSR server and set SSR_TEST_URL to execute the real Node integration.');
