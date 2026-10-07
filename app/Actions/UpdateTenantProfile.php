<?php

namespace App\Actions;

use App\Models\PlatformAdmin;
use App\Models\TenantAuditLog;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateTenantProfile
{
    /** @param array<string, mixed> $attributes */
    public function handle(User|PlatformAdmin $actor, TenantProfile $profile, array $attributes): TenantProfile
    {
        Gate::forUser($actor)->authorize('update', $profile);
        $data = Validator::make($attributes, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($actor, $profile, $data): TenantProfile {
            $profile = TenantProfile::query()->lockForUpdate()->findOrFail($profile->id);
            Gate::forUser($actor)->authorize('update', $profile);
            $before = $profile->only(array_keys($data));
            $profile->update($data);
            $audit = new TenantAuditLog([
                'action' => 'profile.updated', 'subject_type' => 'tenant_profile',
                'subject_id' => $profile->id,
                'changes' => ['before' => $before, 'after' => $profile->only(array_keys($data))],
            ]);
            $audit->actor()->associate($actor);
            $audit->save();

            return $profile;
        });
    }
}
