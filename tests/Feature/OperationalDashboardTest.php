<?php

use App\Actions\ReadOperationalDashboard;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00 UTC'));
});

test('studio dashboard counts seats without averaging rates and preserves session context', function (string $role) {
    $this->prepareStudio();
    $actor = User::factory()->for($this->studio)->withTwoFactor()->create(['role' => $role]);
    $small = ClassSession::factory()->create(['status' => 'completed', 'capacity' => 2]);
    $large = ClassSession::factory()->create(['status' => 'published', 'capacity' => 8]);
    $cancelled = ClassSession::factory()->create(['status' => 'cancelled', 'capacity' => 99]);
    ClassSession::factory()->create(['status' => 'draft', 'capacity' => 99]);
    ClassSession::factory()->create(['status' => 'published', 'starts_at' => '2026-11-12 10:00:00', 'ends_at' => '2026-11-12 11:00:00']);
    $present = Booking::factory()->for($small, 'session')->create();
    AttendanceRecord::factory()->for($present)->create(['status' => 'present', 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
    $absent = Booking::factory()->for($small, 'session')->create();
    AttendanceRecord::factory()->for($absent)->create(['status' => 'no_show', 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
    Booking::factory()->for($large, 'session')->create();
    $pending = Booking::factory()->for($large, 'session')->create();
    AttendanceRecord::factory()->for($pending)->create();
    $late = Booking::factory()->for($large, 'session')->cancelled()->create(['cancellation_reason' => 'customer_late']);
    AttendanceRecord::factory()->for($late)->create(['status' => 'present', 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
    Booking::factory()->for($large, 'session')->cancelled()->create();
    Booking::factory()->for($cancelled, 'session')->cancelled()->create(['cancellation_reason' => 'studio_cancelled']);
    $small->trainingType->forceFill(['capacity' => 50])->save();

    $this->signInToStudio($actor)->get('http://lotus.yoga.test/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('studio/Dashboard')->where('scope', 'studio')
        ->where('period', ['from' => '2026-10-12', 'to' => '2026-10-18', 'timezone' => 'Europe/Zagreb'])
        ->where('summary', ['sessions' => 2, 'cancelled' => 1, 'capacity' => 10, 'confirmed' => 4,
            'available' => 6, 'pending' => 2, 'present' => 1, 'no_show' => 1, 'late_cancelled' => 1, 'occupancy' => 40])
        ->has('sessions.data', 3)->where('sessions.data.0.capacity', 2)->where('sessions.data.0.status', 'completed')
        ->where('sessions.data.2.available', null)->missing('sessions.data.0.bookings'));
})->with(['owner', 'manager']);

test('instructor dashboard cannot widen its scope using query identifiers', function () {
    $this->prepareStudio();
    $actor = User::factory()->for($this->studio)->create(['role' => 'instructor']);
    $own = ClassSession::factory()->create(['status' => 'published', 'capacity' => 4]);
    $own->instructor->forceFill(['user_id' => $actor->id])->save();
    ClassSession::factory()->create(['status' => 'published', 'capacity' => 20]);
    $otherTenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($otherTenant);
    ClassSession::factory()->create(['status' => 'published', 'capacity' => 30]);

    $this->signInToStudio($actor)->get('http://lotus.yoga.test/dashboard?tenant_id='.$otherTenant->id.'&instructor_profile_id=0&scope=all')
        ->assertInertia(fn (Assert $page) => $page->where('scope', 'own')->where('summary.capacity', 4)
            ->has('sessions.data', 1)->where('sessions.data.0.id', $own->id));
});

test('empty scope differs from a real zero occupancy and instructors without profiles see no sessions', function () {
    $this->prepareStudio();
    $actor = User::factory()->for($this->studio)->create(['role' => 'instructor']);
    ClassSession::factory()->create(['status' => 'published']);
    $this->signInToStudio($actor)->get('http://lotus.yoga.test/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('summary.capacity', 0)->where('summary.occupancy', null)->where('summary.pending', 0)->has('sessions.data', 0));
    app(TenantContext::class)->setTenant($this->studio);
    $actor->forceFill(['role' => 'manager'])->save();
    $this->get('http://lotus.yoga.test/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('summary.capacity', 10)->where('summary.occupancy', 0)->where('summary.confirmed', 0));
});

test('date filters include the entire local DST day and exclude its next midnight', function () {
    $this->prepareStudio();
    $actor = User::factory()->for($this->studio)->create(['role' => 'manager']);
    foreach (['2026-10-24 21:59:59', '2026-10-24 22:00:00', '2026-10-25 22:59:59', '2026-10-25 23:00:00'] as $start) {
        ClassSession::factory()->create(['status' => 'published', 'starts_at' => $start, 'ends_at' => CarbonImmutable::parse($start)->addHour()]);
    }

    $this->signInToStudio($actor)->get('http://lotus.yoga.test/dashboard?from=2026-10-25&to=2026-10-25')
        ->assertInertia(fn (Assert $page) => $page->where('summary.sessions', 2)->has('sessions.data', 2)
            ->where('sessions.data.0.local_start', '2026-10-25 00:00')->where('sessions.data.1.local_start', '2026-10-25 23:59'));
});

test('studio summary covers all pages and ignores another tenant', function () {
    $this->prepareStudio();
    $actor = User::factory()->for($this->studio)->create(['role' => 'manager']);
    ClassSession::factory()->count(26)->create(['status' => 'published', 'capacity' => 2]);
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    ClassSession::factory()->create(['status' => 'published', 'capacity' => 99]);

    $this->signInToStudio($actor)->get('http://lotus.yoga.test/dashboard?page=2&tenant_id='.$other->id)
        ->assertInertia(fn (Assert $page) => $page->where('summary.capacity', 52)->where('sessions.total', 26)->has('sessions.data', 1));
});

test('invalid dashboard periods return 422', function (string $query, string $field) {
    $this->prepareStudio();
    $actor = User::factory()->for($this->studio)->create(['role' => 'manager']);
    $this->signInToStudio($actor)->getJson('http://lotus.yoga.test/dashboard?'.$query)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['from=invalid', 'from'], ['from=2026-02-30', 'from'], ['to=invalid', 'to'],
    ['from=2026-10-12&to=2026-10-11', 'to'], ['from=2026-01-01&to=2027-01-02', 'to'], ['page=0', 'page'],
]);

test('customer and unauthenticated requests cannot read staff reports', function () {
    $this->prepareStudio();
    $this->get('http://lotus.yoga.test/dashboard')->assertRedirect('http://lotus.yoga.test/login');
    app(TenantContext::class)->setTenant($this->studio);
    $actor = User::factory()->for($this->studio)->create(['role' => 'customer']);
    $this->signInToStudio($actor)->get('http://lotus.yoga.test/dashboard')->assertForbidden();
    URL::forceRootUrl('http://platform.yoga.test');
    $this->get('http://platform.yoga.test/platform/dashboard')->assertRedirect('http://platform.yoga.test/platform/login');
    $this->get('http://unknown.yoga.test/dashboard')->assertNotFound();
});

test('dashboard action fails closed without matching tenant context', function () {
    $this->prepareStudio();
    $actor = User::factory()->for($this->studio)->create(['role' => 'manager']);
    app(TenantContext::class)->clear();
    expect(fn () => app(ReadOperationalDashboard::class)->studio($actor, []))->toThrow(AuthorizationException::class);
    app(TenantContext::class)->setTenant(Tenant::factory()->active()->create());
    expect(fn () => app(ReadOperationalDashboard::class)->studio($actor, []))->toThrow(AuthorizationException::class);
});

test('platform reports weighted aggregates across all pages without personal lists', function () {
    $this->prepareStudio();
    $small = ClassSession::factory()->create(['status' => 'published', 'capacity' => 2]);
    Booking::factory()->count(2)->for($small, 'session')->create();
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    $large = ClassSession::factory()->create(['status' => 'published', 'capacity' => 8]);
    Booking::factory()->count(2)->for($large, 'session')->create();
    Tenant::factory()->count(24)->create();
    $admin = PlatformAdmin::factory()->create([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['report-test-recovery'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $response = $this->post('http://platform.yoga.test/platform/login', ['email' => $admin->email, 'password' => 'password']);
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue())->withCredentials();
    $response = $this->post('http://platform.yoga.test/platform/two-factor-challenge', ['recovery_code' => 'report-test-recovery']);
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());

    $this->get('http://platform.yoga.test/platform/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('platform/Dashboard')->where('summary.capacity', 10)->where('summary.confirmed', 4)->where('summary.occupancy', 40)
        ->where('tenants.total', 26)->has('tenants.data', 25)->where('tenants.data.0.metrics.occupancy', 100)
        ->where('tenants.data.1.metrics.occupancy', 25)->where('tenants.data.2.metrics.occupancy', null)
        ->missing('sessions')->missing('tenants.data.0.users')->missing('tenants.data.0.bookings')
        ->has('tenants.data.0', 4));
    $this->get('http://platform.yoga.test/platform/dashboard?page=2')->assertInertia(fn (Assert $page) => $page
        ->where('summary.occupancy', 40)->where('summary.capacity', 10)->has('tenants.data', 1));
});

test('platform report action requires active verified platform identity in platform context', function () {
    $this->prepareStudio();
    $admin = PlatformAdmin::factory()->create();
    app(TenantContext::class)->setPlatform();
    expect(fn () => app(ReadOperationalDashboard::class)->platform($admin, []))->toThrow(AuthorizationException::class);
    $admin->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
    app(TenantContext::class)->setTenant($this->studio);
    expect(fn () => app(ReadOperationalDashboard::class)->platform($admin, []))->toThrow(AuthorizationException::class);
    app(TenantContext::class)->setPlatform();
    $admin->forceFill(['is_active' => false])->save();
    expect(fn () => app(ReadOperationalDashboard::class)->platform($admin, []))->toThrow(AuthorizationException::class);
});
