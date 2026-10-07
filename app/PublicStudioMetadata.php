<?php

namespace App;

use App\Models\TenantDomain;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Str;

class PublicStudioMetadata
{
    /** @return array{title: string, description: string, canonical: ?string, robots: string} */
    public function forPage(PublicStudioProfile $profile, string $page): array
    {
        $hostname = TenantDomain::query()
            ->where('is_primary', true)->where('is_active', true)
            ->where('verification_status', 'verified')->whereNotNull('verified_at')
            ->value('normalized_hostname');
        $canonical = null;

        if (is_string($hostname)) {
            $generator = clone app(UrlGenerator::class);
            $scheme = app()->isProduction() ? 'https' : config('tenancy.auth_scheme');
            $port = config('tenancy.auth_port');
            $generator->forceRootUrl($scheme.'://'.$hostname.($port ? ':'.$port : ''));
            $generator->forceScheme($scheme);
            $canonical = $generator->route($page);
        }

        $indexable = app()->isProduction() && config('tenancy.public_indexing') && is_string($hostname)
            && $hostname === request()->getHost()
            && ! Str::endsWith($hostname, ['.test', '.localhost', '.local', '.invalid', '.example'])
            && $hostname !== 'localhost';

        return [
            'title' => $page === 'about' ? 'O studiju — '.$profile->name : $profile->name,
            'description' => $profile->shortDescription ?: 'Upoznajte '.$profile->name.'. Informacije o studiju i kontakt.',
            'canonical' => $canonical,
            'robots' => $indexable ? 'index, follow' : 'noindex, nofollow',
        ];
    }
}
