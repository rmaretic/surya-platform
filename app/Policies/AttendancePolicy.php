<?php

namespace App\Policies;

use App\Auth\AdministrativeAccess;
use App\Models\ClassSession;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\TenantContext;

class AttendancePolicy
{
    public function manage(User|PlatformAdmin $user, ClassSession $session): bool
    {
        if (! $user instanceof User || ! (new AdministrativeAccess)->studio($user)
            || $session->tenant_id !== app(TenantContext::class)->tenant()?->id) {
            return false;
        }

        return in_array($user->role, ['owner', 'manager'], true)
            || $session->instructor()->where('tenant_id', $user->tenant_id)->where('user_id', $user->id)->whereNull('archived_at')->exists();
    }
}
