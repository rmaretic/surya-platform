<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OwnerInvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(StaffInvitation $invitation): Response
    {
        $this->ensureAvailable($invitation);

        return Inertia::render('auth/AcceptOwnerInvitation', ['invitationId' => $invitation->id]);
    }

    public function store(Request $request, StaffInvitation $invitation): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ]);
        try {
            DB::transaction(function () use ($invitation, $data): void {
                $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
                abort_unless($tenant->status === 'active', 404);
                $invitation = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
                $this->ensureAvailable($invitation);
                if (! hash_equals($invitation->token_hash, hash('sha256', $data['token']))) {
                    throw ValidationException::withMessages(['token' => 'Pozivnica nije valjana. Otvorite najnoviji poziv iz emaila.']);
                }
                if (User::query()->where('normalized_email', $invitation->normalized_email)->exists()) {
                    throw ValidationException::withMessages(['token' => 'Račun s tom adresom već postoji u studiju. Obratite se operatoru.']);
                }
                $user = new User(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
                $user->forceFill(['role' => $invitation->role, 'is_active' => true, 'email_verified_at' => now()]);
                $user->save();
                $invitation->accepted_at = now();
                $invitation->recipient()->associate($user);
                $invitation->save();
                $audit = new TenantAuditLog([
                    'action' => $invitation->role === 'owner' ? 'owner_invitation.accepted' : 'staff_invitation.accepted', 'subject_type' => 'staff_invitation',
                    'subject_id' => $invitation->id, 'changes' => ['role' => $invitation->role, 'user_id' => $user->id],
                ]);
                $audit->actor()->associate($user);
                $audit->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['token' => 'Račun s tom adresom već postoji u studiju. Obratite se operatoru.']);
        }

        return to_route('login')->with('status', $invitation->role === 'owner'
            ? 'Poziv je prihvaćen. Prijavite se i postavite obvezni 2FA.'
            : 'Poziv je prihvaćen. Prijavite se u svoj studio.');
    }

    protected function ensureAvailable(StaffInvitation $invitation): void
    {
        abort_unless($invitation->role === 'owner' && $invitation->invited_by_platform_admin_id !== null
            && $invitation->sent_at !== null && $invitation->accepted_at === null
            && $invitation->revoked_at === null && $invitation->expires_at->isFuture(), 404);
    }
}
