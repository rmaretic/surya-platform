<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporary boundary for unfinished settings until M1-08.
 */
class RequireTenantAuthenticationReady
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('/', 'about', 'up', '_boost/browser-logs', '_inertia/devtools/*', 'platform/tenants', 'platform/tenants/*', 'owner-invitations/*', 'studio/*', 'staff-invitations/*')
            || preg_match('#^(platform/)?(login|logout|register|forgot-password|reset-password(?:/[^/]+)?|email/verify(?:/[^/]+/[^/]+)?|email/verification-notification|account|dashboard|portal|two-factor-challenge|settings/two-factor|user/(?:confirm-password|confirmed-password-status|two-factor-authentication|confirmed-two-factor-authentication|two-factor-qr-code|two-factor-secret-key|two-factor-recovery-codes))$#', $request->path())) {
            return $next($request);
        }

        return response('Prijava i administracija su u pripremi.', 503, ['Retry-After' => '3600']);
    }
}
