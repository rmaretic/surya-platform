<?php

namespace App\Http\Middleware;

use App\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ConfigureAuthentication
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $platform = app(TenantContext::class)->isPlatform();
        $values = [
            'fortify.guard' => $platform ? 'platform' : 'web',
            'fortify.passwords' => $platform ? 'platform_admins' : 'users',
            'fortify.home' => $platform ? '/platform/account' : '/account',
            'fortify.redirects.password-reset' => $platform ? '/platform/login' : '/login',
        ];
        $previous = [];
        foreach ($values as $key => $value) {
            $previous[$key] = config($key);
        }
        $guard = Auth::getDefaultDriver();
        config($values);
        Auth::shouldUse($values['fortify.guard']);
        app('session')->driver()->flush();
        try {
            return $next($request);
        } finally {
            config($previous);
            Auth::shouldUse($guard);
        }
    }
}
