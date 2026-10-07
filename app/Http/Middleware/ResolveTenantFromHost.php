<?php

namespace App\Http\Middleware;

use App\Actions\NormalizeHostname;
use App\Models\Scopes\TenantScope;
use App\Models\TenantDomain;
use App\TenantContext;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromHost
{
    public function __construct(private TenantContext $context, private NormalizeHostname $normalizer) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        try {
            try {
                $hostname = $this->normalizer->handle($request->getHost());
            } catch (InvalidArgumentException|SuspiciousOperationException) {
                return response('Not found.', 404);
            }

            if ($hostname === $this->normalizer->handle(config('tenancy.platform_domain'))) {
                $this->context->setPlatform();
            } else {
                $domain = TenantDomain::withoutGlobalScope(TenantScope::class)
                    ->with('tenant')
                    ->where('normalized_hostname', $hostname)
                    ->where('is_active', true)
                    ->where('verification_status', 'verified')
                    ->whereNotNull('verified_at')
                    ->first();

                if ($domain === null || $domain->tenant === null) {
                    return response('Not found.', 404);
                }

                if ($domain->tenant->status !== 'active') {
                    return response('Service unavailable.', 503, ['Retry-After' => '3600']);
                }

                $this->context->setTenant($domain->tenant);
            }

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
