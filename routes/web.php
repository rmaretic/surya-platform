<?php

use App\Http\Controllers\Auth\TwoFactorSettingsController;
use App\Http\Controllers\OwnerInvitationController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\PublicStudioController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\StudioCatalogController;
use App\Http\Controllers\StudioController;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\ProtectTwoFactorAuthentication;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicStudioController::class, 'home'])->name('home');
Route::get('about', [PublicStudioController::class, 'about'])->name('about');

require __DIR__.'/auth.php';

foreach (['web' => '', 'platform' => 'platform'] as $guard => $prefix) {
    Route::prefix($prefix)->name($prefix === '' ? '' : 'platform.')
        ->middleware(['auth:'.$guard, $prefix === '' ? 'verified' : 'verified:platform.verification.notice'])
        ->group(function () use ($prefix): void {
            Route::get('settings/two-factor', [TwoFactorSettingsController::class, 'show'])
                ->middleware([ProtectTwoFactorAuthentication::class, 'password.confirm:'.($prefix === '' ? '' : 'platform.').'password.confirm'])
                ->name('two-factor.settings');
            if ($prefix === '') {
                Route::get('account', [StudioController::class, 'account'])->middleware(EnsureTwoFactorEnrolled::class)->name('account');
                Route::get('dashboard', [StudioController::class, 'dashboard'])
                    ->middleware([EnsureTwoFactorEnrolled::class, 'can:studio-administration'])->name('dashboard');
            } else {
                Route::inertia('account', 'auth/Account')->middleware(EnsureTwoFactorEnrolled::class)->name('account');
                Route::get('dashboard', [PlatformTenantController::class, 'index'])
                    ->middleware([EnsureTwoFactorEnrolled::class, 'can:viewAny,App\Models\Tenant'])->name('dashboard');
            }
        });
}
Route::get('portal', [StudioController::class, 'account'])
    ->middleware(['auth:web', 'verified', 'can:customer-portal'])->name('portal');

require __DIR__.'/settings.php';

Route::prefix('studio')->name('studio.')
    ->middleware(['auth:web', 'verified', EnsureTwoFactorEnrolled::class, 'can:studio-administration'])
    ->group(function (): void {
        Route::get('profile', [StudioController::class, 'profile'])->name('profile.edit');
        Route::get('catalog', [StudioCatalogController::class, 'index'])->name('catalog.index');
        Route::post('catalog/training-types', [StudioCatalogController::class, 'storeTrainingType'])->name('catalog.training-types.store');
        Route::patch('catalog/training-types/{trainingType}', [StudioCatalogController::class, 'updateTrainingType'])->name('catalog.training-types.update');
        Route::post('catalog/rooms', [StudioCatalogController::class, 'storeRoom'])->name('catalog.rooms.store');
        Route::patch('catalog/rooms/{room}', [StudioCatalogController::class, 'updateRoom'])->name('catalog.rooms.update');
        Route::post('catalog/instructors', [StudioCatalogController::class, 'storeInstructor'])->name('catalog.instructors.store');
        Route::patch('catalog/instructors/{instructor}', [StudioCatalogController::class, 'updateInstructor'])->name('catalog.instructors.update');
        Route::post('catalog/packages', [StudioCatalogController::class, 'storePackage'])->name('catalog.packages.store');
        Route::patch('catalog/packages/{package}', [StudioCatalogController::class, 'updatePackage'])->name('catalog.packages.update');
        Route::post('catalog/rules', [StudioCatalogController::class, 'storeRules'])->name('catalog.rules.store');
        Route::patch('profile', [StudioController::class, 'updateProfile'])->name('profile.update');
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff/invitations', [StaffController::class, 'invite'])->middleware('throttle:auth-sensitive')->name('staff.invite');
        Route::post('staff/invitations/{invitation}/send', [StaffController::class, 'send'])->middleware('throttle:auth-sensitive')->name('staff.send');
        Route::post('staff/invitations/{invitation}/revoke', [StaffController::class, 'revoke'])->name('staff.revoke');
        Route::patch('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::post('staff/{staff}/deactivate', [StaffController::class, 'deactivate'])->name('staff.deactivate');
    });

Route::prefix('platform/tenants')->name('platform.tenants.')
    ->middleware(['auth:platform', 'verified:platform.verification.notice', EnsureTwoFactorEnrolled::class, 'can:viewAny,App\Models\Tenant'])
    ->group(function (): void {
        Route::get('/', [PlatformTenantController::class, 'index'])->name('index');
        Route::get('create', [PlatformTenantController::class, 'create'])->name('create');
        Route::post('/', [PlatformTenantController::class, 'store'])->name('store');
        Route::get('{tenant}', [PlatformTenantController::class, 'show'])->name('show');
        Route::patch('{tenant}', [PlatformTenantController::class, 'update'])->name('update');
        Route::post('{tenant}/domains', [PlatformTenantController::class, 'addDomain'])->name('domains.store');
        Route::post('{tenant}/domains/{domain}/verify', [PlatformTenantController::class, 'verifyDomain'])->whereNumber('domain')->name('domains.verify');
        Route::post('{tenant}/suspend', [PlatformTenantController::class, 'suspend'])->name('suspend');
        Route::post('{tenant}/owner-invitation', [PlatformTenantController::class, 'sendInvitation'])->middleware('throttle:auth-sensitive')->name('invitation.send');
    });

Route::middleware(['guest:web', 'throttle:owner-invitation'])->group(function (): void {
    Route::get('staff-invitations/{invitation}', [StaffInvitationController::class, 'show'])->name('staff-invitations.show');
    Route::post('staff-invitations/{invitation}', [StaffInvitationController::class, 'store'])->name('staff-invitations.store');
    Route::get('owner-invitations/{invitation}', [OwnerInvitationController::class, 'show'])->name('owner-invitations.show');
    Route::post('owner-invitations/{invitation}', [OwnerInvitationController::class, 'store'])->name('owner-invitations.store');
});
