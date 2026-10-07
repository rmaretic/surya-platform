<?php

use App\Http\Middleware\BindSessionIdentity;
use App\Http\Middleware\ConfigureAuthentication;
use App\Http\Middleware\EnsureDomainArea;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTenantAuthenticationReady;
use App\Http\Middleware\ResolveTenantFromHost;
use App\Http\Middleware\TrustExplicitProxies;
use App\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove(TrustProxies::class);
        $middleware->prepend([
            TrustExplicitProxies::class,
            ResolveTenantFromHost::class,
            EnsureDomainArea::class,
            RequireTenantAuthenticationReady::class,
            ConfigureAuthentication::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->redirectGuestsTo(fn () => route(app(TenantContext::class)->isPlatform() ? 'platform.login' : 'login'));
        $middleware->redirectUsersTo(fn () => route(app(TenantContext::class)->isPlatform() ? 'platform.account' : 'account'));
        $middleware->appendToPriorityList(StartSession::class, BindSessionIdentity::class);

        $middleware->web(append: [
            BindSessionIdentity::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'code', 'recovery_code', 'two_factor_secret', 'two_factor_recovery_codes']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
