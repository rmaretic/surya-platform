<?php

namespace App\Http\Controllers;

use App\Actions\ManageStudioStaff;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('studio/Staff', [
            'staff' => User::query()->whereIn('role', ['owner', 'manager', 'instructor'])->orderBy('id')->paginate(20, ['id', 'name', 'email', 'role', 'is_active']),
            'invitations' => StaffInvitation::query()->whereIn('role', ['manager', 'instructor'])->latest('id')->paginate(20, ['id', 'email', 'role', 'expires_at', 'accepted_at', 'revoked_at', 'sent_at', 'delivery_failed_at'], 'invitations_page'),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function invite(Request $request, ManageStudioStaff $manage): RedirectResponse
    {
        $actor = $this->actor($request);
        $invitation = $manage->invite($actor, $request->only(['email', 'role']));
        $sent = $manage->send($actor, $invitation);

        return $this->deliveryResponse($sent);
    }

    public function send(Request $request, StaffInvitation $invitation, ManageStudioStaff $manage): RedirectResponse
    {
        return $this->deliveryResponse($manage->send($this->actor($request), $invitation));
    }

    public function revoke(Request $request, StaffInvitation $invitation, ManageStudioStaff $manage): RedirectResponse
    {
        $manage->revoke($this->actor($request), $invitation);

        return to_route('studio.staff.index')->with('status', 'Pozivnica je opozvana.');
    }

    public function update(Request $request, User $staff, ManageStudioStaff $manage): RedirectResponse
    {
        Gate::authorize('viewAny', User::class);
        $data = $request->validate(['role' => ['required', 'string', Rule::in(['manager', 'instructor'])]]);
        $manage->changeRole($this->actor($request), $staff, $data['role']);

        return to_route('studio.staff.index')->with('status', 'Uloga je spremljena.');
    }

    public function deactivate(Request $request, User $staff, ManageStudioStaff $manage): RedirectResponse
    {
        Gate::authorize('deactivate', $staff);
        $request->validate(['confirmed' => ['accepted']]);
        $manage->deactivate($this->actor($request), $staff);

        return to_route('studio.staff.index')->with('status', 'Djelatnik je deaktiviran i pristup je ukinut.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function deliveryResponse(bool $sent): RedirectResponse
    {
        return to_route('studio.staff.index')->with('status', $sent ? 'Pozivnica je poslana. Vrijedi sedam dana.' : 'Slanje nije uspjelo. Pozivnica je sačuvana; pokušajte ponovno.');
    }
}
