<?php

namespace App\Policies;

use App\Auth\AdministrativeAccess;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\TenantContext;

class StudioCatalogPolicy
{
    public function manage(User|PlatformAdmin $user): bool
    {
        return $user instanceof User && $user->tenant_id === app(TenantContext::class)->tenant()?->id
            && in_array($user->role, ['owner', 'manager'], true) && AdministrativeAccess::ready($user);
    }

    public function pricing(User|PlatformAdmin $user): bool
    {
        return $user instanceof User && $this->manage($user) && $user->role === 'owner';
    }
}
