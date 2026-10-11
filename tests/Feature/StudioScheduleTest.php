<?php

use App\Actions\ManageStudioSchedule;
use App\Models\Booking;
use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\ScheduleSeries;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function scheduleActor(string $role = 'manager'): User
{
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test', 'is_primary' => true]);
    TenantProfile::factory()->for($tenant)->create();

    return User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
}

function scheduleData(array $overrides = []): array
{
    BookingRuleVersion::factory()->create();

    return [...[
        'training_type_id' => TrainingType::factory()->create()->id,
        'instructor_profile_id' => InstructorProfile::factory()->create()->id,
        'room_id' => Room::factory()->create(['capacity' => 20])->id,
        'duration_minutes' => 60, 'capacity' => 10,
        'local_start' => '2026-10-12 18:00', 'status' => 'published',
    ], ...$overrides];
}

function scheduleSeries(array $data, array $overrides = []): ScheduleSeries
{
    return ScheduleSeries::factory()->create([
        ...collect($data)->only(['training_type_id', 'instructor_profile_id', 'room_id', 'duration_minutes', 'capacity'])->all(),
        'booking_rule_version_id' => BookingRuleVersion::query()->firstOrFail()->id,
        ...$overrides,
    ]);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00:00', 'UTC'));
});

test('manager creates edits and reads a group session with UTC instants and audit', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    $this->signInToStudio($actor);
    $this->post('http://lotus.yoga.test/studio/schedule/sessions', $data)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseHas('class_sessions', ['starts_at' => '2026-10-12 16:00:00', 'ends_at' => '2026-10-12 17:00:00', 'tenant_id' => $actor->tenant_id, 'status' => 'published']);
    $session = ClassSession::query()->sole();
    $this->patch("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", [...$data, 'capacity' => 12, 'version' => 1])->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'capacity' => 12, 'version' => 2]);
    $this->assertDatabaseCount('tenant_audit_logs', 2);
    $this->get('http://lotus.yoga.test/studio/schedule')->assertInertia(fn (Assert $page) => $page->component('studio/Schedule')->where('timezone', 'Europe/Zagreb')->has('sessions.data', 1)->where('sessions.data.0.local_start', '2026-10-12 18:00')->where('sessions.data.0.confirmed_count', 0));
    app(TenantContext::class)->setTenant($actor->tenant);
});

test('schedule refuses unprivileged roles', function (string $role) {
    $actor = scheduleActor($role);
    $this->signInToStudio($actor);
    $this->postJson('http://lotus.yoga.test/studio/schedule/sessions', [])->assertForbidden();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->get('http://lotus.yoga.test/studio/schedule')->assertForbidden();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('class_sessions', 0);
})->with(['instructor', 'customer']);

test('schedule requires an authenticated identity', function () {
    $actor = scheduleActor();
    $this->postJson('http://lotus.yoga.test/studio/schedule/sessions', [])->assertUnauthorized();
    app(TenantContext::class)->setTenant($actor->tenant);
});

test('schedule rejects foreign resources and bound records and client tenant context', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    $foreign = ClassSession::factory()->create();
    $series = ScheduleSeries::factory()->create();
    $this->signInToStudio($actor);
    $this->patchJson("http://lotus.yoga.test/studio/schedule/sessions/{$foreign->id}", $data)->assertNotFound();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->postJson("http://lotus.yoga.test/studio/schedule/series/{$series->id}/generate")->assertNotFound();
    app(TenantContext::class)->setTenant($actor->tenant);
    foreach (['training_type_id', 'instructor_profile_id', 'room_id'] as $field) {
        $this->postJson('http://lotus.yoga.test/studio/schedule/sessions', [...$data, $field => $foreign->$field])->assertUnprocessable()->assertJsonValidationErrors($field);
        app(TenantContext::class)->setTenant($actor->tenant);
    }
    $this->postJson('http://lotus.yoga.test/studio/schedule/sessions', [...$data, 'tenant_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('tenant_id');
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->get('http://lotus.yoga.test/studio/schedule')->assertInertia(fn (Assert $page) => $page->has('sessions.data', 0)->has('series', 0));
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
});

test('weekly generation preserves local hour across both DST transitions and is idempotent', function (string $today, string $from, string $before, string $after) {
    $this->travelTo(CarbonImmutable::parse($today, 'UTC'));
    $actor = scheduleActor();
    $data = scheduleData();
    $series = scheduleSeries($data, ['starts_on' => $from]);
    $this->signInToStudio($actor);
    $url = "http://lotus.yoga.test/studio/schedule/series/{$series->id}/generate";
    $this->post($url)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->post($url)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('class_sessions', 8);
    $this->assertDatabaseHas('class_sessions', ['starts_at' => $before, 'status' => 'draft']);
    $this->assertDatabaseHas('class_sessions', ['starts_at' => $after, 'status' => 'draft']);
    expect(ClassSession::query()->get()->every(fn ($item) => $item->starts_at->setTimezone('Europe/Zagreb')->format('H:i') === '18:00'))->toBeTrue();
})->with([
    ['2026-10-10 10:00:00', '2026-10-12', '2026-10-19 16:00:00', '2026-10-26 17:00:00'],
    ['2026-03-14 10:00:00', '2026-03-16', '2026-03-23 17:00:00', '2026-03-30 16:00:00'],
]);

test('DST generation rolls back and requires explicit occurrence correction', function (string $today, string $date, string $time, ?string $offset, string $expected) {
    $this->travelTo(CarbonImmutable::parse($today, 'UTC'));
    $actor = scheduleActor();
    $series = scheduleSeries(scheduleData(), ['starts_on' => $date, 'ends_on' => $date, 'weekdays' => [7], 'local_start_time' => '02:30:00']);
    $this->signInToStudio($actor);
    $url = "http://lotus.yoga.test/studio/schedule/series/{$series->id}/generate";
    $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('corrections');
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('class_sessions', 0);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
    $this->post($url, ['corrections' => [['date' => $date, 'time' => $time, 'offset' => $offset]]])->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseHas('class_sessions', ['occurrence_key' => $date, 'starts_at' => $expected]);
    $this->post($url)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('class_sessions', 1);
})->with([
    ['2026-03-14', '2026-03-29', '03:30', null, '2026-03-29 01:30:00'],
    ['2026-10-10', '2026-10-25', '02:30', '+01:00', '2026-10-25 01:30:00'],
    ['2026-10-10', '2026-10-25', '02:30', '+02:00', '2026-10-25 00:30:00'],
]);

test('UTC duration remains real elapsed time through DST', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-14', 'UTC'));
    $actor = scheduleActor();
    $data = scheduleData(['local_start' => '2026-03-29 01:30', 'duration_minutes' => 120]);
    $this->signInToStudio($actor);
    $this->post('http://lotus.yoga.test/studio/schedule/sessions', $data)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseHas('class_sessions', ['starts_at' => '2026-03-29 00:30:00', 'ends_at' => '2026-03-29 02:30:00']);
});

test('resource conflicts include private buffers and allow touching protected boundaries', function (string $resource) {
    $actor = scheduleActor();
    $data = scheduleData(['local_start' => '2026-10-12 19:00']);
    ClassSession::factory()->create([$resource => $data[$resource], 'buffer_after_minutes' => 15]);
    $this->signInToStudio($actor);
    $this->postJson('http://lotus.yoga.test/studio/schedule/sessions', $data)->assertUnprocessable()->assertJsonValidationErrors('local_start');
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->post('http://lotus.yoga.test/studio/schedule/sessions', [...$data, 'local_start' => '2026-10-12 19:15'])->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('class_sessions', 2);
})->with(['room_id', 'instructor_profile_id']);

test('cancelled occurrence is not regenerated or revived', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    $series = scheduleSeries($data, ['ends_on' => '2026-10-12']);
    $this->signInToStudio($actor);
    $url = "http://lotus.yoga.test/studio/schedule/series/{$series->id}/generate";
    $this->post($url)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $session = ClassSession::query()->sole();
    $this->patch("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", [...$data, 'status' => 'cancelled', 'cancellation_reason' => 'Praznik'])->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->post($url)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->patchJson("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", $data)->assertUnprocessable();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('class_sessions', 1);
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'cancelled', 'cancellation_reason' => 'Praznik']);
});

test('confirmed bookings protect time duration service status and occupied capacity', function (array $change, string $error) {
    $actor = scheduleActor();
    $data = scheduleData();
    app(ManageStudioSchedule::class)->saveSession($actor, new ClassSession, $data);
    $session = ClassSession::query()->sole();
    Booking::factory()->count(2)->create(['class_session_id' => $session->id]);
    $this->signInToStudio($actor);
    $this->patchJson("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", [...$data, ...$change])->assertUnprocessable()->assertJsonValidationErrors($error);
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'version' => 1, 'capacity' => 10, 'status' => 'published', 'starts_at' => '2026-10-12 16:00:00']);
    $this->assertDatabaseCount('outbox_events', 0);
})->with([
    [['local_start' => '2026-10-12 19:00'], 'local_start'],
    [['duration_minutes' => 90], 'local_start'],
    [['status' => 'cancelled', 'cancellation_reason' => 'Razlog'], 'local_start'],
    [['status' => 'draft'], 'local_start'],
    [['capacity' => 1], 'capacity'],
]);

test('resource replacement records one versioned outbox event per booking and preserves snapshots', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    app(ManageStudioSchedule::class)->saveSession($actor, new ClassSession, $data);
    $session = ClassSession::query()->sole();
    $booking = Booking::factory()->create(['class_session_id' => $session->id]);
    $replacement = InstructorProfile::factory()->create();
    $this->signInToStudio($actor);
    $changed = [...$data, 'instructor_profile_id' => $replacement->id];
    $this->patch("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", $changed)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->patch("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", $changed)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('outbox_events', 1);
    $this->assertDatabaseHas('outbox_events', ['booking_id' => $booking->id, 'recipient_user_id' => $booking->user_id, 'aggregate_version' => 2, 'event_type' => 'session.resources_changed', 'status' => 'pending']);
    expect($booking->fresh()->rules_snapshot)->toEqual($booking->rules_snapshot);
    $this->assertDatabaseCount('credit_entries', 0);
});

test('future edits split a series and preserve booked cancelled and earlier occurrences', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    $series = scheduleSeries($data);
    app(ManageStudioSchedule::class)->generate($actor, $series, []);
    $sessions = ClassSession::query()->orderBy('starts_at')->get();
    $sessions[2]->forceFill(['status' => 'published'])->save();
    Booking::factory()->create(['class_session_id' => $sessions[2]->id]);
    $sessions[3]->forceFill(['status' => 'cancelled', 'cancellation_reason' => 'Praznik', 'cancelled_at' => now()])->save();
    $this->signInToStudio($actor);
    $this->patch("http://lotus.yoga.test/studio/schedule/sessions/{$sessions[1]->id}", [...$data, 'scope' => 'future', 'local_start' => '2026-10-19 19:00'])->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $successor = ScheduleSeries::query()->orderByDesc('id')->firstOrFail();
    $this->assertDatabaseHas('class_sessions', ['id' => $sessions[0]->id, 'schedule_series_id' => $series->id, 'starts_at' => '2026-10-12 16:00:00']);
    $this->assertDatabaseHas('class_sessions', ['id' => $sessions[1]->id, 'starts_at' => '2026-10-19 17:00:00', 'status' => 'published']);
    $this->assertDatabaseHas('class_sessions', ['id' => $sessions[2]->id, 'starts_at' => '2026-10-26 17:00:00', 'status' => 'published']);
    $this->assertDatabaseHas('class_sessions', ['id' => $sessions[3]->id, 'status' => 'cancelled']);
    $this->travelTo(CarbonImmutable::parse('2026-10-20', 'UTC'));
    $this->signInToStudio($actor);
    $this->post("http://lotus.yoga.test/studio/schedule/series/{$successor->id}/generate")->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseHas('class_sessions', ['occurrence_key' => '2026-12-07', 'starts_at' => '2026-12-07 18:00:00']);
    expect(DB::table('class_sessions')->where('occurrence_key', '2026-11-02')->count())->toBe(1);
});

test('generator conflict rolls back the entire batch', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    $series = scheduleSeries($data);
    ClassSession::factory()->create(['room_id' => $data['room_id'], 'starts_at' => '2026-10-26 17:00:00', 'ends_at' => '2026-10-26 18:00:00']);
    $this->signInToStudio($actor);
    $this->postJson("http://lotus.yoga.test/studio/schedule/series/{$series->id}/generate")->assertUnprocessable();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('class_sessions', 1);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
});

test('closing a series keeps sessions and prevents further generation', function () {
    $actor = scheduleActor();
    $series = scheduleSeries(scheduleData());
    app(ManageStudioSchedule::class)->generate($actor, $series, []);
    $this->signInToStudio($actor);
    $this->post("http://lotus.yoga.test/studio/schedule/series/{$series->id}/close")->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->postJson("http://lotus.yoga.test/studio/schedule/series/{$series->id}/generate")->assertUnprocessable()->assertJsonValidationErrors('series');
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->assertDatabaseCount('class_sessions', 8);
});

test('series creation validates days dates resources and ignores protected attributes', function () {
    $actor = scheduleActor('owner');
    $data = scheduleData();
    $this->signInToStudio($actor);
    $this->post('http://lotus.yoga.test/studio/schedule/series', [...$data, 'weekdays' => [1, 3], 'local_start_time' => '18:00', 'starts_on' => '2026-10-12', 'ends_on' => '2026-11-12', 'timezone' => 'UTC', 'archived_at' => now(), 'booking_rule_version_id' => 999999])->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $series = ScheduleSeries::query()->sole();
    expect($series->timezone)->toBe('Europe/Zagreb');
    expect($series->weekdays)->toBe([1, 3]);
    expect($series->archived_at)->toBeNull();
    expect($series->booking_rule_version_id)->not->toBe(999999);
    $this->assertDatabaseCount('class_sessions', 0);
    $this->postJson('http://lotus.yoga.test/studio/schedule/series', [...$data, 'weekdays' => [0, 1, 1], 'local_start_time' => '25:00', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-11'])->assertUnprocessable()->assertJsonValidationErrors(['weekdays.0', 'weekdays.1', 'local_start_time', 'ends_on']);
    $this->assertDatabaseCount('schedule_series', 1);
});

test('invalid schedule values do not write sessions', function (string $field, mixed $value) {
    $actor = scheduleActor();
    $data = scheduleData();
    $this->signInToStudio($actor);
    $this->postJson('http://lotus.yoga.test/studio/schedule/sessions', [...$data, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('class_sessions', 0);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
})->with([
    ['duration_minutes', 0], ['duration_minutes', 481], ['capacity', 0], ['capacity', 21],
    ['local_start', '2026-02-30 18:00'], ['local_start', '2026-10-09 18:00'],
    ['local_start', '2026-10-25 02:30'], ['utc_offset', '+09:00'], ['status', 'completed'],
]);

test('inactive and private resources cannot be scheduled', function (string $kind, string $field) {
    $actor = scheduleActor();
    $data = scheduleData();
    match ($kind) {
        'private' => TrainingType::query()->whereKey($data['training_type_id'])->update(['mode' => 'private']),
        'type' => TrainingType::query()->whereKey($data['training_type_id'])->update(['archived_at' => now()]),
        'room' => Room::query()->whereKey($data['room_id'])->update(['archived_at' => now()]),
        'instructor' => InstructorProfile::query()->whereKey($data['instructor_profile_id'])->update(['archived_at' => now()]),
        'user' => InstructorProfile::query()->findOrFail($data['instructor_profile_id'])->user->forceFill(['is_active' => false])->save(),
    };
    $this->signInToStudio($actor);
    $this->postJson('http://lotus.yoga.test/studio/schedule/sessions', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('class_sessions', 0);
})->with([['private', 'training_type_id'], ['type', 'training_type_id'], ['room', 'capacity'], ['instructor', 'instructor_profile_id'], ['user', 'instructor_profile_id']]);

test('stale edits preserve the latest version', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    app(ManageStudioSchedule::class)->saveSession($actor, new ClassSession, $data);
    $session = ClassSession::query()->sole();
    $this->signInToStudio($actor);
    $this->patch("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", [...$data, 'capacity' => 12, 'version' => 1])->assertRedirect();
    $this->patchJson("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", [...$data, 'capacity' => 8, 'version' => 1])->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'capacity' => 12, 'version' => 2]);
});

test('a conflict in future edits restores original series and every interval', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    $series = scheduleSeries($data);
    app(ManageStudioSchedule::class)->generate($actor, $series, []);
    $first = ClassSession::query()->orderBy('starts_at')->firstOrFail();
    ClassSession::factory()->create(['room_id' => $data['room_id'], 'starts_at' => '2026-10-26 18:00:00', 'ends_at' => '2026-10-26 19:00:00']);
    $this->signInToStudio($actor);
    $this->patchJson("http://lotus.yoga.test/studio/schedule/sessions/{$first->id}", [...$data, 'scope' => 'future', 'local_start' => '2026-10-12 19:00'])->assertUnprocessable()->assertJsonValidationErrors('local_start');
    $this->assertDatabaseCount('schedule_series', 1);
    $this->assertDatabaseHas('schedule_series', ['id' => $series->id, 'archived_at' => null, 'ends_on' => null]);
    $this->assertDatabaseHas('class_sessions', ['id' => $first->id, 'status' => 'draft', 'starts_at' => '2026-10-12 16:00:00', 'version' => 1]);
    expect(DB::table('class_sessions')->where('status', 'cancelled')->count())->toBe(0);
});

test('all schedule operations lock the existing tenant row before target rows', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    $series = scheduleSeries($data);
    $locks = [];
    DB::listen(function ($query) use (&$locks) {
        if (str_contains(strtolower($query->sql), 'for update')) {
            $locks[] = $query->sql;
        }
    });
    $manage = app(ManageStudioSchedule::class);
    $manage->generate($actor, $series, []);
    expect($locks[0])->toContain('`tenants`');
    $locks = [];
    $session = ClassSession::query()->firstOrFail();
    $manage->saveSession($actor, $session, [...$data, 'capacity' => 11]);
    expect($locks[0])->toContain('`tenants`');
    $locks = [];
    $manage->createSeries($actor, [...$data, 'starts_on' => '2026-10-12', 'weekdays' => [2], 'local_start_time' => '18:00']);
    expect($locks[0])->toContain('`tenants`');
    $locks = [];
    $manage->closeSeries($actor, $series);
    expect($locks[0])->toContain('`tenants`');
});

test('booked service changes and conflicting replacements leave booking and outbox intact', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    app(ManageStudioSchedule::class)->saveSession($actor, new ClassSession, $data);
    $session = ClassSession::query()->sole();
    $booking = Booking::factory()->create(['class_session_id' => $session->id]);
    $otherType = TrainingType::factory()->create();
    $replacement = InstructorProfile::factory()->create();
    ClassSession::factory()->create(['instructor_profile_id' => $replacement->id]);
    $this->signInToStudio($actor);
    $this->patchJson("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", [...$data, 'training_type_id' => $otherType->id])->assertUnprocessable()->assertJsonValidationErrors('local_start');
    $this->patchJson("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", [...$data, 'instructor_profile_id' => $replacement->id])->assertUnprocessable()->assertJsonValidationErrors('local_start');
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'instructor_profile_id' => $data['instructor_profile_id'], 'version' => 1]);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    $this->assertDatabaseCount('outbox_events', 0);
});

test('group endpoints cannot edit private sessions', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    $privateType = TrainingType::factory()->create(['mode' => 'private', 'capacity' => 1]);
    $session = ClassSession::factory()->create(['training_type_id' => $privateType->id, 'capacity' => 1]);
    $this->signInToStudio($actor);
    $this->patchJson("http://lotus.yoga.test/studio/schedule/sessions/{$session->id}", $data)->assertNotFound();
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'training_type_id' => $privateType->id]);
});

test('new schedules require existing owner rules', function () {
    $actor = scheduleActor();
    $data = scheduleData();
    BookingRuleVersion::query()->delete();
    $this->signInToStudio($actor);
    $this->postJson('http://lotus.yoga.test/studio/schedule/sessions', $data)->assertUnprocessable()->assertJsonValidationErrors('rules');
    $this->assertDatabaseCount('class_sessions', 0);
    $this->assertDatabaseCount('booking_rule_versions', 0);
});

test('owner without completed two factor cannot change schedules', function () {
    $actor = scheduleActor('owner');
    $actor->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
    $data = scheduleData();
    $this->signInToStudio($actor);
    $this->post('http://lotus.yoga.test/studio/schedule/sessions', $data)->assertRedirect('http://lotus.yoga.test/settings/two-factor');
    $this->assertDatabaseCount('class_sessions', 0);
});
