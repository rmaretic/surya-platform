<?php

namespace App\Jobs\Middleware;

use App\Models\Tenant;
use App\TenantContext;
use Closure;
use LogicException;

class UseTenantContext
{
    public function __construct(public readonly int $tenantId) {}

    /** @param Closure(object): mixed $next */
    public function handle(object $job, Closure $next): mixed
    {
        $context = app(TenantContext::class);
        $context->clear();
        try {
            $tenant = Tenant::query()->findOrFail($this->tenantId);
            if ($tenant->status !== 'active') {
                throw new LogicException('The queued studio is not active.');
            }
            $context->setTenant($tenant);

            return $next($job);
        } finally {
            $context->clear();
        }
    }
}
