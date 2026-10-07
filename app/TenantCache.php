<?php

namespace App;

use Closure;
use Illuminate\Support\Facades\Cache;

class TenantCache
{
    /** @template TValue
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $type, string $key, int $seconds, Closure $callback): mixed
    {
        return Cache::remember($this->key($type, $key), $seconds, $callback);
    }

    public function forget(string $type, string $key): bool
    {
        return Cache::forget($this->key($type, $key));
    }

    private function key(string $type, string $key): string
    {
        return 'tenant:'.app(TenantContext::class)->requireTenant()->id.':'.hash('sha256', serialize([$type, $key]));
    }
}
