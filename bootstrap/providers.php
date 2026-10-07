<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthenticationServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AuthenticationServiceProvider::class,
    FortifyServiceProvider::class,
];
