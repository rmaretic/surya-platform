<?php

namespace App\Auth;

use App\TenantContext;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Database\Query\Builder;

class TenantTokenRepository extends DatabaseTokenRepository
{
    protected function getTable(): Builder
    {
        return parent::getTable()->where('tenant_id', app(TenantContext::class)->requireTenant()->id);
    }

    /** @param string $email
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getPayload($email, #[\SensitiveParameter] $token): array
    {
        return [...parent::getPayload($email, $token), 'tenant_id' => app(TenantContext::class)->requireTenant()->id];
    }
}
