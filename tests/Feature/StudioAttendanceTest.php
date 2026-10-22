<?php

use App\Actions\CreateBooking;
use App\Actions\ManageAttendance;
use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../BookingFixtures.php';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00 UTC'));
});

function attendanceSetup(string $role = 'manager'): array
{
    [$tenant, $customer, $session] = bookingSetup();
    $booking = app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $actor = User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
    if ($role === 'instructor') {
        $session->instructor->forceFill(['user_id' => $actor->id])->save();
    }

    return [$tenant, $customer, $session, $booking, $actor, 'http://booking-'.$tenant->id.'.yoga.test/studio/attendance'];
}

function attendanceData(string $status = 'present', string $expected = 'pending', ?string $reason = null): array
{
    return ['status' => $status, 'expected_status' => $expected, 'reason' => $reason, 'operation_key' => (string) Str::uuid()];
}

test('staff records attendance once with author time audit and no additional debit', function (string $role) {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup($role);
    $this->signInToStudio($actor);
    $data = attendanceData();
    $this->post($url.'/'.$booking->id, $data)->assertRedirect();
    $this->travel(1)->hours();
    $this->post($url.'/'.$booking->id, $data)->assertRedirect();
    $this->post($url.'/'.$booking->id, attendanceData())->assertRedirect();
    $this->assertDatabaseCount('attendance_records', 1);
    $this->assertDatabaseHas('attendance_records', ['booking_id' => $booking->id, 'status' => 'present', 'recorded_by_user_id' => $actor->id, 'recorded_at' => '2026-10-12 10:00:00']);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseCount('outbox_events', 1);
    app(TenantContext::class)->setTenant($tenant);
    expect(TenantAuditLog::where('action', 'studio.attendance_recorded')->count())->toBe(2);
    $this->get($url.'?date=2026-10-19&session='.$session->id)->assertInertia(fn (Assert $page) => $page
        ->component('studio/Attendance')->has('sessions', 1)->has('selected.bookings', 1)
        ->where('selected.bookings.0.name', $customer->name)->where('selected.bookings.0.recorded_by', $actor->name)
        ->missing('selected.bookings.0.email')->missing('selected.bookings.0.grant')->missing('selected.bookings.0.correction_reason'));
})->with(['owner', 'manager', 'instructor']);

test('no show requires the session end and remains explicitly pending until confirmed', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    $this->signInToStudio($actor);
    $this->travelTo(CarbonImmutable::parse('2026-10-19 10:59:59 UTC'));
    $this->signInToStudio($actor);
    $this->postJson($url.'/'.$booking->id, attendanceData('no_show'))->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseCount('attendance_records', 0);
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:00:00 UTC'));
    $this->get($url.'?session='.$session->id)->assertInertia(fn (Assert $page) => $page
        ->where('selected.can_mark_no_show', true)->where('selected.bookings.0.status', 'pending'));
    $this->assertDatabaseCount('attendance_records', 0);
    $this->post($url.'/'.$booking->id, attendanceData('no_show'))->assertRedirect();
    $this->assertDatabaseHas('attendance_records', ['booking_id' => $booking->id, 'status' => 'no_show', 'recorded_at' => '2026-10-19 11:00:00']);
    $this->assertDatabaseCount('credit_entries', 2);
});

test('correction requires a reason preserves audit and retry cannot undo later correction', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    $this->signInToStudio($actor);
    $data = attendanceData();
    $this->post($url.'/'.$booking->id, $data)->assertRedirect();
    $this->postJson($url.'/'.$booking->id, attendanceData('pending', 'present', '   '))->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->post($url.'/'.$booking->id, attendanceData('pending', 'present', 'Pogrešan polaznik'))->assertRedirect();
    $this->post($url.'/'.$booking->id, $data)->assertRedirect();
    $this->assertDatabaseHas('attendance_records', ['booking_id' => $booking->id, 'status' => 'pending', 'recorded_by_user_id' => null, 'recorded_at' => null, 'correction_reason' => 'Pogrešan polaznik']);
    app(TenantContext::class)->setTenant($tenant);
    $audit = TenantAuditLog::where('action', 'studio.attendance_recorded')->orderByDesc('id')->firstOrFail();
    expect($audit->changes)->toMatchArray(['from' => 'present', 'to' => 'pending', 'reason' => 'Pogrešan polaznik']);
    expect($audit->actor_user_id)->toBe($actor->id);
    expect(TenantAuditLog::where('action', 'studio.attendance_recorded')->count())->toBe(2);
    $this->assertDatabaseCount('credit_entries', 2);
});

test('stale correction and reused operation with changed payload are rejected', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    $this->signInToStudio($actor);
    $data = attendanceData();
    $this->post($url.'/'.$booking->id, $data)->assertRedirect();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00 UTC'));
    $this->signInToStudio($actor);
    $this->postJson($url.'/'.$booking->id, attendanceData('no_show'))->assertUnprocessable()->assertJsonValidationErrors('expected_status');
    $this->postJson($url.'/'.$booking->id, [...$data, 'status' => 'no_show'])->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $this->assertDatabaseHas('attendance_records', ['booking_id' => $booking->id, 'status' => 'present']);
});

test('cancelled bookings and draft or cancelled sessions reject check in', function (string $state) {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    if ($state === 'booking') {
        $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->id, 'cancellation_reason' => 'customer_late'])->save();
    } else {
        $session->forceFill(['status' => $state])->save();
    }
    $this->signInToStudio($actor);
    $this->postJson($url.'/'.$booking->id, attendanceData())->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseCount('attendance_records', 0);
    $this->assertDatabaseCount('credit_entries', 2);
})->with(['booking', 'draft', 'cancelled']);

test('instructor cannot read or record another instructors session', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup('instructor');
    $otherSession = ClassSession::factory()->create(['starts_at' => '2026-10-19 13:00:00', 'ends_at' => '2026-10-19 14:00:00']);
    $this->signInToStudio($actor);
    $this->get($url.'?date=2026-10-19')->assertInertia(fn (Assert $page) => $page->has('sessions', 1)->where('sessions.0.id', $session->id));
    $this->get($url.'?session='.$otherSession->id)->assertNotFound();
    app(TenantContext::class)->setTenant($tenant);
    $session->forceFill(['instructor_profile_id' => $otherSession->instructor_profile_id])->save();
    $this->postJson($url.'/'.$booking->id, attendanceData())->assertForbidden();
    $this->assertDatabaseCount('attendance_records', 0);
});

test('foreign tenant ids cannot expose or modify attendance', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    [$otherTenant, $otherCustomer, $otherSession, $otherBooking] = attendanceSetup();
    $this->signInToStudio($actor);
    $this->get($url.'?session='.$otherSession->id)->assertNotFound();
    $this->postJson($url.'/'.$otherBooking->id, attendanceData())->assertNotFound();
    $this->assertDatabaseCount('attendance_records', 0);
});

test('customer cannot access staff attendance', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    $this->signInToStudio($customer);
    $this->get($url)->assertForbidden();
    $this->postJson($url.'/'.$booking->id, attendanceData())->assertForbidden();
    $this->assertDatabaseCount('attendance_records', 0);
});

test('guests cannot access attendance', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    $this->get($url)->assertRedirect();
    $this->postJson($url.'/'.$booking->id, attendanceData())->assertUnauthorized();
    $this->assertDatabaseCount('attendance_records', 0);
});

test('attendance ignores client tenant actor and time and validates allowed states', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    $this->signInToStudio($actor);
    $this->postJson($url.'/'.$booking->id, [])->assertUnprocessable()->assertJsonValidationErrors(['status', 'expected_status', 'operation_key']);
    $this->postJson($url.'/'.$booking->id, attendanceData('unknown'))->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->post($url.'/'.$booking->id, [...attendanceData(), 'tenant_id' => 99999, 'recorded_by_user_id' => $customer->id, 'recorded_at' => '2000-01-01'])->assertRedirect();
    $this->assertDatabaseHas('attendance_records', ['booking_id' => $booking->id, 'tenant_id' => $tenant->id, 'recorded_by_user_id' => $actor->id, 'recorded_at' => '2026-10-12 10:00:00']);
});

test('a repeated no change operation cannot overwrite a subsequent correction', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup();
    $this->signInToStudio($actor);
    $this->post($url.'/'.$booking->id, attendanceData())->assertRedirect();
    $noChange = attendanceData();
    $this->post($url.'/'.$booking->id, $noChange)->assertRedirect();
    $this->post($url.'/'.$booking->id, attendanceData('pending', 'present', 'Provjeriti popis'))->assertRedirect();
    $this->post($url.'/'.$booking->id, $noChange)->assertRedirect();
    $this->assertDatabaseHas('attendance_records', ['booking_id' => $booking->id, 'status' => 'pending']);
    $this->assertDatabaseCount('attendance_records', 1);
});

test('completed session allows correction of an explicit pending record and no show', function () {
    [$tenant, $customer, $session, $booking, $actor, $url] = attendanceSetup('instructor');
    $session->forceFill(['status' => 'completed'])->save();
    AttendanceRecord::factory()->for($booking)->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00 UTC'));
    $this->signInToStudio($actor);
    $this->post($url.'/'.$booking->id, attendanceData('no_show'))->assertRedirect();
    $this->post($url.'/'.$booking->id, attendanceData('present', 'no_show', 'Naknadno potvrđen dolazak'))->assertRedirect();
    $this->assertDatabaseHas('attendance_records', ['booking_id' => $booking->id, 'status' => 'present', 'recorded_by_user_id' => $actor->id, 'correction_reason' => 'Naknadno potvrđen dolazak']);
    $this->assertDatabaseCount('attendance_records', 1);
    $this->assertDatabaseCount('credit_entries', 2);
});

test('attendance policy rejects inactive unverified and unenrolled staff and archived instructors', function (string $restriction) {
    [$tenant, $customer, $session, $booking, $actor] = attendanceSetup($restriction === 'unenrolled' ? 'owner' : 'instructor');
    if ($restriction === 'archived') {
        $session->instructor->forceFill(['archived_at' => now()])->save();
    } else {
        $actor->forceFill(match ($restriction) {
            'inactive' => ['is_active' => false],
            'unverified' => ['email_verified_at' => null],
            'unenrolled' => ['two_factor_confirmed_at' => null],
        })->save();
    }
    expect(Gate::forUser($actor)->allows('studio-attendance', $session))->toBeFalse();
    expect(fn () => app(ManageAttendance::class)->handle($actor, $booking, attendanceData()))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('attendance_records', 0);
})->with(['inactive', 'unverified', 'unenrolled', 'archived']);
