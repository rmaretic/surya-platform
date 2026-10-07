<?php

namespace App\Policies;

use App\Auth\AdministrativeAccess;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\TenantContext;

class TenantPolicy
{
    public function viewAny(User|PlatformAdmin $user): bool
    {
        return $user instanceof PlatformAdmin && AdministrativeAccess::ready($user) && app(TenantContext::class)->isPlatform();
    }

    public function view(User|PlatformAdmin $user, Tenant $tenant): bool
    {
        return $this->viewAny($user);
    }

    public function create(User|PlatformAdmin $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User|PlatformAdmin $user, Tenant $tenant): bool
    {
        return $this->viewAny($user);
    }
}
