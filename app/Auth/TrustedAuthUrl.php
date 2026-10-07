<?php

namespace App\Auth;

use App\Models\PlatformAdmin;
use App\Models\Scopes\TenantScope;
use App\Models\StaffInvitation;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Routing\UrlGenerator;
use LogicException;

class TrustedAuthUrl
{
    public function invitation(StaffInvitation $invitation, string $token): string
    {
        $identity = new User;
        $identity->tenant_id = $invitation->tenant_id;

        $route = $invitation->role === 'owner' ? 'owner-invitations.show' : 'staff-invitations.show';

        return $this->generator($identity)->route($route, ['invitation' => $invitation->id]).'#token='.$token;
    }

    public function reset(User|PlatformAdmin $user, string $token): string
    {
        return $this->generator($user)->route($user instanceof PlatformAdmin ? 'platform.password.reset' : 'password.reset', [
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ]);
    }

    public function verification(User|PlatformAdmin $user): string
    {
        return $this->generator($user)->temporarySignedRoute(
            $user instanceof PlatformAdmin ? 'platform.verification.verify' : 'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );
    }

    private function generator(User|PlatformAdmin $user): UrlGenerator
    {
        if ($user instanceof PlatformAdmin) {
            $hostname = config('tenancy.platform_domain');
        } else {
            $hostname = TenantDomain::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $user->tenant_id)->where('is_primary', true)
                ->where('is_active', true)->where('verification_status', 'verified')->whereNotNull('verified_at')
                ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
                ->value('normalized_hostname') ?? throw new LogicException('No trusted primary domain for this identity.');
        }
        $generator = clone app(UrlGenerator::class);
        $scheme = app()->isProduction() ? 'https' : config('tenancy.auth_scheme');
        $port = config('tenancy.auth_port');
        $generator->forceRootUrl($scheme.'://'.$hostname.($port ? ':'.$port : ''));
        $generator->forceScheme($scheme);

        return $generator;
    }
}
