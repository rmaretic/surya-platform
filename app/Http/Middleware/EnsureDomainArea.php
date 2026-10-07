<?php

namespace App\Http\Middleware;

use App\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDomainArea
{
    public function __construct(private TenantContext $context) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('/', 'up', '_boost/browser-logs', '_inertia/devtools/*')) {
            return $next($request);
        }

        if ($request->is('platform', 'platform/*') !== $this->context->isPlatform()) {
            return response('Not found.', 404);
        }

        return $next($request);
    }
}
