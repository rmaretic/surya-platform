<?php

namespace App\Http\Middleware;

use App\Auth\AdministrativeAccess;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\TenantContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProtectTwoFactorAuthentication
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $platform = app(TenantContext::class)->isPlatform();
        $prefix = $platform ? 'platform.' : '';
        if ($request->routeIs($prefix.'two-factor.confirm')) {
            $request->validate(['code' => ['required', 'string', 'max:32']]);
        }
        if ($request->routeIs($prefix.'password.confirm.store')) {
            $request->validate(['password' => ['required', 'string']]);
        }
        if ($request->routeIs($prefix.'two-factor.login', $prefix.'two-factor.login.store')) {
            $response = DB::transaction(function () use ($request, $next, $platform, $prefix): Response {
                $model = $platform ? PlatformAdmin::class : User::class;
                $user = $model::query()->whereKey($request->session()->get('login.id'))->lockForUpdate()->first();
                if (! $user || ! $user->is_active || ! $user->hasEnabledTwoFactorAuthentication()) {
                    $request->session()->forget(['login.id', 'login.remember']);

                    return redirect()->route($prefix.'login');
                }

                return $next($request);
            });
        } else {
            if ($request->routeIs($prefix.'two-factor.disable')) {
                abort_if(AdministrativeAccess::requiresTwoFactor($request->user()), 403);
            }
            $response = $next($request);
        }

        if ($platform && $response instanceof RedirectResponse) {
            foreach (['login', 'two-factor.login', 'password.confirm'] as $name) {
                if ($response->getTargetUrl() === route($name)) {
                    $response->setTargetUrl(route('platform.'.$name));
                }
            }
        }
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
