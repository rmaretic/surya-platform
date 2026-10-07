<?php

use App\Http\Middleware\ProtectTwoFactorAuthentication;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

$fortifyGuard = config('fortify.guard');
$fortifyFeatures = config('fortify.features');

foreach (['web' => '', 'platform' => 'platform'] as $guard => $prefix) {
    config([
        'fortify.guard' => $guard,
        'fortify.features' => $guard === 'platform'
            ? array_values(array_diff($fortifyFeatures, [Features::registration()]))
            : $fortifyFeatures,
    ]);
    Route::middleware(ProtectTwoFactorAuthentication::class)->prefix($prefix)->name($prefix === '' ? '' : 'platform.')->group(function (): void {
        require base_path('vendor/laravel/fortify/routes/routes.php');
    });
}

config(['fortify.guard' => $fortifyGuard, 'fortify.features' => $fortifyFeatures]);

Route::getRoutes()->refreshNameLookups();
foreach (['password.email', 'password.update', 'register.store', 'platform.password.email', 'platform.password.update'] as $name) {
    Route::getRoutes()->getByName($name)?->middleware('throttle:auth-email');
}

foreach (['', 'platform.'] as $prefix) {
    foreach (['two-factor.enable', 'two-factor.confirm', 'two-factor.disable', 'two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes', 'two-factor.regenerate-recovery-codes'] as $name) {
        $route = Route::getRoutes()->getByName($prefix.$name);
        $route->middleware($prefix === '' ? 'verified' : 'verified:platform.verification.notice');
        if ($prefix !== '') {
            $route->withoutMiddleware('password.confirm')->middleware('password.confirm:platform.password.confirm');
        }
    }
    foreach (['two-factor.confirm', 'password.confirm.store', 'two-factor.regenerate-recovery-codes'] as $name) {
        Route::getRoutes()->getByName($prefix.$name)->middleware('throttle:auth-sensitive');
    }
}
