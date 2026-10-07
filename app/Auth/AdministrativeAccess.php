<?php

namespace App\Auth;

use App\Models\PlatformAdmin;
use App\Models\User;
use App\TenantContext;

class AdministrativeAccess
{
    public static function requiresTwoFactor(User|PlatformAdmin $user): bool
    {
        return $user instanceof PlatformAdmin || $user->role === 'owner';
    }

    public static function ready(User|PlatformAdmin $user): bool
    {
        return $user->is_active && $user->hasVerifiedEmail()
            && (! self::requiresTwoFactor($user) || $user->hasEnabledTwoFactorAuthentication());
    }

    public function studio(User|PlatformAdmin $user): bool
    {
        return $user instanceof User && $user->tenant_id === app(TenantContext::class)->tenant()?->id
            && in_array($user->role, ['owner', 'manager', 'instructor'], true) && self::ready($user);
    }

    public function portal(User|PlatformAdmin $user): bool
    {
        return $user instanceof User && $user->tenant_id === app(TenantContext::class)->tenant()?->id
            && $user->role === 'customer' && self::ready($user);
    }
}
