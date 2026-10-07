<?php

namespace App\Actions;

use App\Models\PlatformAdmin;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ListPlatformTenants
{
    /** @return LengthAwarePaginator<int, Tenant> */
    public function handle(User|PlatformAdmin $actor, string $search = ''): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Tenant::class);

        return Tenant::query()->when($search !== '', function ($query) use ($search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('domains', fn ($domains) => $domains
                        ->withoutGlobalScope(TenantScope::class)
                        ->where('normalized_hostname', 'like', '%'.$search.'%'));
            });
        })->orderBy('id')->paginate(25, ['id', 'name', 'status', 'design_key'])->withQueryString();
    }
}
