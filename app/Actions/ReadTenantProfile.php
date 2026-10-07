<?php

namespace App\Actions;

use App\Models\PlatformAdmin;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ReadTenantProfile
{
    public function handle(User|PlatformAdmin $actor, TenantProfile $profile): TenantProfile
    {
        Gate::forUser($actor)->authorize('view', $profile);

        return $profile;
    }
}
