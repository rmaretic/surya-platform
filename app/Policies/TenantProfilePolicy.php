<?php

namespace App\Policies;

use App\Auth\AdministrativeAccess;
use App\Models\PlatformAdmin;
use App\Models\TenantProfile;
use App\Models\User;
use App\TenantContext;
use Illuminate\Auth\Access\Response;

class TenantProfilePolicy
{
    public function view(User|PlatformAdmin $user, TenantProfile $profile): Response
    {
        $tenant = app(TenantContext::class)->tenant();
        if (! $user instanceof User || $tenant === null || $profile->tenant_id !== $tenant->id || $user->tenant_id !== $tenant->id) {
            return Response::denyAsNotFound();
        }

        return AdministrativeAccess::ready($user) && $user->role === 'owner' ? Response::allow() : Response::deny();
    }

    public function update(User|PlatformAdmin $user, TenantProfile $profile): Response
    {
        return $this->view($user, $profile);
    }
}
