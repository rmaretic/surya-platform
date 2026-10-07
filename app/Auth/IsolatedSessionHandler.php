<?php

namespace App\Auth;

use App\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Session\DatabaseSessionHandler;

class IsolatedSessionHandler extends DatabaseSessionHandler
{
    protected function getQuery(): Builder
    {
        $context = app(TenantContext::class);
        if ($context->isPlatform()) {
            return $this->connection->table('platform_sessions')->useWritePdo();
        }

        return $this->connection->table('tenant_sessions')->useWritePdo()
            ->where('tenant_id', $context->requireTenant()->id);
    }

    /** @param string $data
     * @return array<string, mixed>
     */
    protected function getDefaultPayload($data): array
    {
        $payload = parent::getDefaultPayload($data);
        if (! app(TenantContext::class)->isPlatform()) {
            $payload['tenant_id'] = app(TenantContext::class)->requireTenant()->id;
        }

        return $payload;
    }

    public function read($sessionId): string|false
    {
        $this->exists = false;

        return parent::read($sessionId);
    }
}
