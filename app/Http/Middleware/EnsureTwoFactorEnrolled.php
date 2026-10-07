<?php

namespace App\Http\Middleware;

use App\Auth\AdministrativeAccess;
use App\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnrolled
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && AdministrativeAccess::requiresTwoFactor($user) && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route(app(TenantContext::class)->isPlatform() ? 'platform.two-factor.settings' : 'two-factor.settings');
        }

        return $next($request);
    }
}
