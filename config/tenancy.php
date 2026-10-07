<?php

return [
    'auth_scheme' => parse_url((string) env('APP_URL', 'http://platform.yoga.test:8000'), PHP_URL_SCHEME),
    'auth_port' => parse_url((string) env('APP_URL', 'http://platform.yoga.test:8000'), PHP_URL_PORT),
    'platform_domain' => env('PLATFORM_DOMAIN', 'platform.yoga.test'),

    'trusted_proxies' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),
];
