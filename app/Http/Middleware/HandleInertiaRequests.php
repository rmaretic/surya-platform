<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        Inertia::flushShared();

        if ($request->routeIs('home', 'about')) {
            Inertia::pullFlashed($request);

            return [
                'name' => config('app.name'),
                'auth' => ['user' => null],
                'errors' => Inertia::always((object) []),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'email_verified_at']),
            ],
            'platformAuth' => app(TenantContext::class)->isPlatform(),
            'studioName' => app(TenantContext::class)->tenant()?->name,
            'studioAccess' => [
                'administration' => $request->user()?->can('studio-administration'),
                'manage' => $request->user()?->can('viewAny', User::class),
                'catalog' => $request->user()?->can('studio-catalog'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
