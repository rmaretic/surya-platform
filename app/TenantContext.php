<?php

namespace App;

use App\Models\Tenant;
use LogicException;

class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $platform = false;

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->platform = false;
    }

    public function setPlatform(): void
    {
        $this->tenant = null;
        $this->platform = true;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function requireTenant(): Tenant
    {
        return $this->tenant ?? throw new LogicException('A tenant context is required.');
    }

    public function isPlatform(): bool
    {
        return $this->platform;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->platform = false;
    }
}
