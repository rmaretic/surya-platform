<?php

namespace Tests;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected Tenant $studio;

    protected function prepareStudio(): void
    {
        $this->studio = Tenant::factory()->active()->create();
        app(TenantContext::class)->setTenant($this->studio);
        TenantDomain::factory()->verified()->for($this->studio)
            ->create(['hostname' => 'lotus.yoga.test', 'is_primary' => true]);
        URL::forceRootUrl('http://lotus.yoga.test');
    }

    protected function signInToStudio(User $user): static
    {
        $domain = TenantDomain::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $user->tenant_id)->value('normalized_hostname');
        $response = $this->post('http://'.$domain.'/login', ['email' => $user->email, 'password' => 'password']);
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $response->assertRedirect('http://'.$domain.'/two-factor-challenge');
            $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
            $response = $this->post('http://'.$domain.'/two-factor-challenge', ['recovery_code' => $user->recoveryCodes()[0]]);
            app(TenantContext::class)->setTenant($user->tenant);
            $user->refresh();
        }
        $response->assertRedirect('/account');
        $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
        $this->withCredentials();

        return $this;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
