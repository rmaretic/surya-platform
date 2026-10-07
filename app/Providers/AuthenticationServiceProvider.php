<?php

namespace App\Providers;

use App\Auth\IsolatedSessionGuard;
use App\Auth\IsolatedSessionHandler;
use App\Auth\TenantPasswordBrokerManager;
use App\Auth\TrustedAuthUrl;
use App\Auth\TwoFactorReplayCache;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider as TwoFactorAuthenticationProviderContract;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

class AuthenticationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(PasswordResetServiceProvider::class);
        $this->app->singleton('auth.password', fn ($app) => new TenantPasswordBrokerManager($app));
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        $this->app->bind(RedirectsIfTwoFactorAuthenticatable::class, RedirectIfTwoFactorAuthenticatable::class);
        $this->app->bind(TwoFactorAuthenticationProviderContract::class, fn () => new TwoFactorAuthenticationProvider(
            app(Google2FA::class), new TwoFactorReplayCache(Cache::store()->getStore()),
        ));
        Auth::provider('active-eloquent', fn ($app, array $config) => (new EloquentUserProvider($app['hash'], $config['model']))
            ->withQuery(fn ($query) => $query->where('is_active', true)));
        Auth::extend('isolated-session', function ($app, string $name, array $config) {
            $guard = new IsolatedSessionGuard($name, Auth::createUserProvider($config['provider']), $app['session.store'],
                rehashOnLogin: config('hashing.rehash_on_login', true), hashKey: config('app.key'));
            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

            return $guard;
        });
        Session::extend('tenant-database', fn ($app) => new IsolatedSessionHandler(
            $app['db']->connection(config('session.connection')), 'tenant_sessions', config('session.lifetime'), $app,
        ));
        ResetPassword::createUrlUsing(fn (User|PlatformAdmin $user, string $token) => app(TrustedAuthUrl::class)->reset($user, $token));
        VerifyEmail::createUrlUsing(fn (User|PlatformAdmin $user) => app(TrustedAuthUrl::class)->verification($user));
    }
}
