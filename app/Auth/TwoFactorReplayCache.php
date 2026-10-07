<?php

namespace App\Auth;

use App\TenantContext;
use Illuminate\Cache\Repository;

class TwoFactorReplayCache extends Repository
{
    protected function itemKey($key): string
    {
        $context = app(TenantContext::class);
        $area = $context->isPlatform() ? 'platform' : 'tenant:'.$context->requireTenant()->id;
        $identity = request()->user()?->getAuthIdentifier() ?? request()->session()->get('login.id');
        abort_if($identity === null, 403);

        return 'two-factor:'.$area.':'.$identity.':'.$key;
    }
}
