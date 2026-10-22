<?php

use App\Http\Controllers\Auth\TwoFactorSettingsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\OwnerInvitationController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\PublicStudioController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\StudioAttendanceController;
use App\Http\Controllers\StudioAvailabilityController;
use App\Http\Controllers\StudioCancellationController;
use App\Http\Controllers\StudioCatalogController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\StudioCreditController;
use App\Http\Controllers\StudioScheduleController;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\ProtectTwoFactorAuthentication;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicStudioController::class, 'home'])->name('home');
Route::get('about', [PublicStudioController::class, 'about'])->name('about');
Route::get('schedule', [CustomerPortalController::class, 'schedule'])->name('schedule');
Route::get('reserve', [CustomerPortalController::class, 'schedule'])
    ->middleware(['auth:web', 'verified', 'can:customer-portal'])->name('reserve');

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
                Route::get('dashboard', [PlatformDashboardController::class, 'index'])
                    ->middleware([EnsureTwoFactorEnrolled::class, 'can:platform.analytics.read'])->name('dashboard');
            }
        });
}
Route::get('portal', [CustomerPortalController::class, 'index'])
    ->middleware(['auth:web', 'verified', 'can:customer-portal'])->name('portal');
Route::post('bookings', [BookingController::class, 'store'])
    ->middleware(['auth:web', 'verified', EnsureTwoFactorEnrolled::class])->name('bookings.store');
Route::middleware(['auth:web', 'verified', EnsureTwoFactorEnrolled::class])->group(function (): void {
    Route::get('bookings/{booking}/cancellation', [BookingController::class, 'cancellation'])->name('bookings.cancellation');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/move', [BookingController::class, 'move'])->name('bookings.move');
});

require __DIR__.'/settings.php';

Route::prefix('studio')->name('studio.')
    ->middleware(['auth:web', 'verified', EnsureTwoFactorEnrolled::class, 'can:studio-administration'])
    ->group(function (): void {
        Route::get('profile', [StudioController::class, 'profile'])->name('profile.edit');
        Route::get('attendance', [StudioAttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance/{booking}', [StudioAttendanceController::class, 'store'])->name('attendance.store');
        Route::get('catalog', [StudioCatalogController::class, 'index'])->name('catalog.index');
        Route::get('credits', [StudioCreditController::class, 'index'])->name('credits.index');
        Route::post('credits', [StudioCreditController::class, 'store'])->name('credits.store');
        Route::post('credits/{grant}/adjust', [StudioCreditController::class, 'adjust'])->name('credits.adjust');
        Route::get('availability', [StudioAvailabilityController::class, 'index'])->name('availability.index');
        Route::post('availability', [StudioAvailabilityController::class, 'store'])->name('availability.store');
        Route::patch('availability/{availability}', [StudioAvailabilityController::class, 'update'])->name('availability.update');
        Route::post('availability/{availability}/exceptions', [StudioAvailabilityController::class, 'exception'])->name('availability.exception');
        Route::post('availability/{availability}/close', [StudioAvailabilityController::class, 'close'])->name('availability.close');
        Route::get('schedule', [StudioScheduleController::class, 'index'])->name('schedule.index');
        Route::get('cancellations', [StudioCancellationController::class, 'index'])->name('cancellations.index');
        Route::post('cancellations/sessions/{session}', [StudioCancellationController::class, 'cancel'])->name('cancellations.cancel');
        Route::post('cancellations/bookings/{booking}/refund', [StudioCancellationController::class, 'refund'])->name('cancellations.refund');
        Route::post('schedule/sessions', [StudioScheduleController::class, 'store'])->name('schedule.store');
        Route::patch('schedule/sessions/{session}', [StudioScheduleController::class, 'update'])->name('schedule.update');
        Route::post('schedule/series', [StudioScheduleController::class, 'storeSeries'])->name('schedule.series.store');
        Route::post('schedule/series/{series}/generate', [StudioScheduleController::class, 'generate'])->name('schedule.series.generate');
        Route::post('schedule/series/{series}/close', [StudioScheduleController::class, 'closeSeries'])->name('schedule.series.close');
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
