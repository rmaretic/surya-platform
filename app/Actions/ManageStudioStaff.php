<?php

namespace App\Actions;

use App\Auth\TrustedAuthUrl;
use App\Models\InstructorProfile;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ManageStudioStaff
{
    /** @param array<string, mixed> $attributes */
    public function invite(User $actor, array $attributes): StaffInvitation
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);
        $data = Validator::make($attributes, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(['manager', 'instructor'])],
        ])->validate();
        Gate::forUser($actor)->authorize('invite', [User::class, $data['role']]);

        return DB::transaction(function () use ($actor, $data): StaffInvitation {
            $this->lockTenant();
            $email = Str::lower(trim($data['email']));
            if (User::query()->where('normalized_email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Račun s tom adresom već postoji u ovom studiju.']);
            }
            if (StaffInvitation::query()->where('normalized_email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->exists()) {
                throw ValidationException::withMessages(['email' => 'Pozivnica već postoji. Ponovite slanje ili je najprije opozovite.']);
            }
            $invitation = new StaffInvitation(['email' => $email, 'role' => $data['role']]);
            $invitation->inviter()->associate($actor);
            $invitation->token_hash = hash('sha256', Str::random(64));
            $invitation->expires_at = now()->addDays(7);
            $invitation->save();
            $this->audit($actor, 'staff_invitation.created', 'staff_invitation', $invitation->id, ['email' => $email, 'role' => $data['role']]);

            return $invitation;
        });
    }

    public function send(User $actor, StaffInvitation $invitation): bool
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        return DB::transaction(function () use ($actor, $invitation): bool {
            $tenant = $this->lockTenant();
            $invitation = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->ensureManageable($invitation);
            if (User::query()->where('normalized_email', $invitation->normalized_email)->exists()) {
                throw ValidationException::withMessages(['invitation' => 'Račun s tom adresom već postoji u ovom studiju. Opozovite pozivnicu.']);
            }
            $token = Str::random(64);
            $url = app(TrustedAuthUrl::class)->invitation($invitation, $token);
            $invitation->token_hash = hash('sha256', $token);
            $invitation->expires_at = now()->addDays(7);
            $invitation->sent_at = null;
            $invitation->delivery_failed_at = null;
            try {
                Notification::route('mail', $invitation->email)->notify(new StaffInvitationNotification($tenant->name, $url, $invitation->role));
                $invitation->sent_at = now();
            } catch (TransportExceptionInterface) {
                $invitation->delivery_failed_at = now();
            }
            $invitation->save();
            $this->audit($actor, $invitation->sent_at ? 'staff_invitation.sent' : 'staff_invitation.delivery_failed', 'staff_invitation', $invitation->id, ['role' => $invitation->role]);

            return $invitation->sent_at !== null;
        });
    }

    public function revoke(User $actor, StaffInvitation $invitation): void
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);
        DB::transaction(function () use ($actor, $invitation): void {
            $this->lockTenant();
            $invitation = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->ensureManageable($invitation);
            $invitation->revoked_at = now();
            $invitation->save();
            $this->audit($actor, 'staff_invitation.revoked', 'staff_invitation', $invitation->id, ['role' => $invitation->role]);
        });
    }

    public function changeRole(User $actor, User $staff, string $role): void
    {
        Gate::forUser($actor)->authorize('updateRole', [$staff, $role]);
        DB::transaction(function () use ($actor, $staff, $role): void {
            $staff = User::query()->lockForUpdate()->findOrFail($staff->id);
            Gate::forUser($actor)->authorize('updateRole', [$staff, $role]);
            $before = $staff->role;
            $staff->role = match ($role) {
                'manager' => 'manager',
                'instructor' => 'instructor',
                default => abort(403),
            };
            $staff->save();
            $this->audit($actor, 'staff.role_changed', 'user', $staff->id, ['before' => $before, 'after' => $role]);
        });
    }

    public function deactivate(User $actor, User $staff): void
    {
        Gate::forUser($actor)->authorize('deactivate', $staff);
        DB::transaction(function () use ($actor, $staff): void {
            $this->lockTenant();
            $staff = User::query()->lockForUpdate()->findOrFail($staff->id);
            Gate::forUser($actor)->authorize('deactivate', $staff);
            $profile = InstructorProfile::query()->where('user_id', $staff->id)->lockForUpdate()->first();
            if ($profile !== null) {
                app(EnsureInstructorCanDeactivate::class)->handle($profile, 'confirmed');
                $profile->update(['archived_at' => $profile->archived_at ?? now()]);
            }
            $staff->is_active = false;
            $staff->remember_token = null;
            $staff->save();
            DB::table('tenant_sessions')->where('tenant_id', $actor->tenant_id)->where('user_id', $staff->id)->delete();
            $this->audit($actor, 'staff.deactivated', 'user', $staff->id, ['is_active' => false]);
        });
    }

    private function lockTenant(): Tenant
    {
        $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
        abort_unless($tenant->status === 'active', 404);

        return $tenant;
    }

    private function ensureManageable(StaffInvitation $invitation): void
    {
        abort_unless(in_array($invitation->role, ['manager', 'instructor'], true)
            && $invitation->invited_by_user_id !== null && $invitation->accepted_at === null && $invitation->revoked_at === null, 404);
    }

    /** @param array<string, mixed> $changes */
    private function audit(User $actor, string $action, string $type, int $id, array $changes): void
    {
        $audit = new TenantAuditLog(['action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes' => $changes]);
        $audit->actor()->associate($actor);
        $audit->save();
    }
}
