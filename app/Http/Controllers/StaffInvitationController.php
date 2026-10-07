<?php

namespace App\Http\Controllers;

use App\Models\StaffInvitation;
use Inertia\Inertia;
use Inertia\Response;

class StaffInvitationController extends OwnerInvitationController
{
    public function show(StaffInvitation $invitation): Response
    {
        $this->ensureAvailable($invitation);

        return Inertia::render('auth/AcceptStaffInvitation', ['invitationId' => $invitation->id]);
    }

    protected function ensureAvailable(StaffInvitation $invitation): void
    {
        abort_unless(in_array($invitation->role, ['manager', 'instructor'], true)
            && $invitation->invited_by_user_id !== null && $invitation->sent_at !== null
            && $invitation->accepted_at === null && $invitation->revoked_at === null
            && $invitation->expires_at->isFuture(), 404);
    }
}
