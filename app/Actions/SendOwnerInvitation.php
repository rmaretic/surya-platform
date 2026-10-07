<?php

namespace App\Actions;

use App\Auth\TrustedAuthUrl;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Notifications\OwnerInvitationNotification;
use App\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendOwnerInvitation
{
    public function handle(PlatformAdmin $actor, Tenant $tenant): bool
    {
        Gate::forUser($actor)->authorize('update', $tenant);

        return DB::transaction(function () use ($actor, $tenant): bool {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            if ($tenant->status !== 'active') {
                throw ValidationException::withMessages(['invitation' => 'Najprije verificirajte primarnu domenu i aktivirajte studio.']);
            }
            $context = app(TenantContext::class);
            $context->setTenant($tenant);
            try {
                $invitation = StaffInvitation::query()->where('role', 'owner')
                    ->whereNotNull('invited_by_platform_admin_id')->whereNull('accepted_at')->whereNull('revoked_at')
                    ->lockForUpdate()->first();
                if (! $invitation) {
                    throw ValidationException::withMessages(['invitation' => 'Nema početne owner pozivnice za slanje.']);
                }
                $token = Str::random(64);
                try {
                    $url = app(TrustedAuthUrl::class)->invitation($invitation, $token);
                } catch (LogicException) {
                    throw ValidationException::withMessages(['invitation' => 'Studio nema aktivnu verificiranu primarnu domenu.']);
                }
                $invitation->token_hash = hash('sha256', $token);
                $invitation->expires_at = now()->addDays(7);
                $invitation->sent_at = null;
                $invitation->delivery_failed_at = null;
                try {
                    Notification::route('mail', $invitation->email)->notify(new OwnerInvitationNotification($tenant->name, $url));
                    $invitation->sent_at = now();
                } catch (TransportExceptionInterface) {
                    $invitation->delivery_failed_at = now();
                }
                $invitation->save();
                $audit = new PlatformAuditLog([
                    'action' => $invitation->sent_at ? 'owner_invitation.sent' : 'owner_invitation.delivery_failed',
                    'subject_type' => 'staff_invitation', 'subject_id' => $invitation->id,
                    'changes' => ['delivery_status' => $invitation->sent_at ? 'sent' : 'failed'],
                ]);
                $audit->actor_platform_admin_id = $actor->id;
                $audit->target_tenant_id = $tenant->id;
                $audit->save();

                return $invitation->sent_at !== null;
            } finally {
                $context->setPlatform();
            }
        });
    }
}
