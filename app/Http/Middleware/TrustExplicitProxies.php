<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class TrustExplicitProxies
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $proxies */
        $proxies = config('tenancy.trusted_proxies', []);

        foreach ($proxies as $proxy) {
            if (! filter_var(explode('/', $proxy, 2)[0], FILTER_VALIDATE_IP)) {
                throw new LogicException('Trusted proxies must be explicit IP addresses or CIDR ranges.');
            }
        }

        Request::setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        return $next($request);
    }
}
