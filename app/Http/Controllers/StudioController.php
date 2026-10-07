<?php

namespace App\Http\Controllers;

use App\Actions\UpdateTenantProfile;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    public function dashboard(Request $request): Response
    {
        Gate::authorize('studio-administration');

        return Inertia::render('studio/Dashboard', ['role' => $this->actor($request)->role]);
    }

    public function account(Request $request): Response
    {
        return Inertia::render('studio/Account', ['role' => $this->actor($request)->role]);
    }

    public function profile(Request $request): Response
    {
        $profile = TenantProfile::query()->firstOrFail();
        Gate::authorize('update', $profile);

        return Inertia::render('studio/Profile', [
            'profile' => $profile->only(['name', 'short_description', 'email', 'phone', 'address']),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function updateProfile(Request $request, UpdateTenantProfile $update): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $update->handle($actor, TenantProfile::query()->firstOrFail(), $request->only(['name', 'short_description', 'email', 'phone', 'address']));

        return to_route('studio.profile.edit')->with('status', 'Javni profil je spremljen. Promjene su odmah vidljive na stranici studija.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
