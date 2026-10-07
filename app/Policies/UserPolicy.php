<?php

namespace App\Policies;

use App\Auth\AdministrativeAccess;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\TenantContext;

class UserPolicy
{
    public function viewAny(User|PlatformAdmin $user): bool
    {
        return $user instanceof User && $user->tenant_id === app(TenantContext::class)->tenant()?->id
            && $user->role === 'owner' && AdministrativeAccess::ready($user);
    }

    public function invite(User|PlatformAdmin $user, string $role): bool
    {
        return $this->viewAny($user) && in_array($role, ['manager', 'instructor'], true);
    }

    public function updateRole(User|PlatformAdmin $user, User $staff, string $role): bool
    {
        return $this->deactivate($user, $staff) && in_array($role, ['manager', 'instructor'], true);
    }

    public function deactivate(User|PlatformAdmin $user, User $staff): bool
    {
        return $user instanceof User && $this->viewAny($user) && $staff->tenant_id === $user->tenant_id
            && in_array($staff->role, ['manager', 'instructor'], true);
    }
}
