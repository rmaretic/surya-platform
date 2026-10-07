<?php

namespace App\Http\Middleware;

use App\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BindSessionIdentity
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $identity = $context->isPlatform() ? 'platform' : 'tenant:'.$context->requireTenant()->id;
        $session = $request->session();
        if ($session->get('auth_context') !== $identity) {
            $session->invalidate();
            $session->regenerateToken();
        }
        $session->put('auth_context', $identity);
        $user = Auth::user();
        if ($user !== null && ! $user->is_active) {
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();
        }

        return $next($request);
    }
}
