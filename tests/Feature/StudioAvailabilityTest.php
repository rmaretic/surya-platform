<?php

use App\Actions\CalculatePrivateSlots;
use App\Actions\ManageStudioAvailability;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function availabilityActor(string $role = 'manager'): User
{
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test', 'is_primary' => true]);
    TenantProfile::factory()->for($tenant)->create();

    return User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
}

function availabilityPayload(?AvailabilityRule $rule = null): array
{
    if ($rule === null) {
        BookingRuleVersion::factory()->create();
    }

    return [
        'training_type_id' => $rule?->training_type_id ?? TrainingType::factory()->create(['mode' => 'private', 'capacity' => 1])->id,
        'instructor_profile_id' => $rule?->instructor_profile_id ?? InstructorProfile::factory()->create()->id,
        'room_id' => $rule?->room_id ?? Room::factory()->create()->id,
        'weekday' => $rule?->weekday ?? 1,
        'local_start_time' => '09:00', 'local_end_time' => '13:00',
        'starts_on' => '2026-10-12', 'ends_on' => null,
        'duration_minutes' => 60, 'slot_step_minutes' => 30,
        'buffer_before_minutes' => 15, 'buffer_after_minutes' => 15,
        'operation_key' => (string) Str::uuid(),
        ...($rule === null ? [] : ['revision' => app(ManageStudioAvailability::class)->revision($rule->fresh())]),
    ];
}

function availabilitySession(AvailabilityRule $rule, array $overrides = []): ClassSession
{
    return ClassSession::factory()->create([
        'training_type_id' => $rule->training_type_id, 'instructor_profile_id' => $rule->instructor_profile_id,
        'room_id' => $rule->room_id, 'booking_rule_version_id' => $rule->booking_rule_version_id,
        'starts_at' => '2026-10-12 08:00:00', 'ends_at' => '2026-10-12 09:00:00',
        'buffer_before_minutes' => 15, 'buffer_after_minutes' => 15, 'capacity' => 1, 'status' => 'published',
        ...$overrides,
    ])->fresh();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00:00', 'UTC'));
});

test('manager creates updates and previews private availability without creating bookings', function () {
    $actor = availabilityActor();
    $data = availabilityPayload();
    $this->signInToStudio($actor);
    $this->post('http://lotus.yoga.test/studio/availability', $data)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $rule = AvailabilityRule::query()->sole();
    $this->assertDatabaseHas('availability_rules', ['id' => $rule->id, 'tenant_id' => $actor->tenant_id, 'timezone' => 'Europe/Zagreb', 'slot_step_minutes' => 30]);
    $data = [...availabilityPayload($rule), 'local_end_time' => '14:00'];
    $this->patch("http://lotus.yoga.test/studio/availability/{$rule->id}", $data)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->get('http://lotus.yoga.test/studio/availability?date=2026-10-12')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('studio/Availability')->where('timezone', 'Europe/Zagreb')->has('availability', 1)
        ->where('availability.0.local_end_time', '14:00')->has('preview.slots', 7)->where('preview.slots.0.local_start', '09:30')
        ->missing('instructors.0.user')->missing('preview.slots.0.user_id'));
    $this->assertDatabaseCount('tenant_audit_logs', 2);
    $this->assertDatabaseCount('class_sessions', 0);
    $this->assertDatabaseCount('bookings', 0);
});

test('availability mutation retries are idempotent and changed payload cannot reuse a key', function () {
    $actor = availabilityActor();
    $data = availabilityPayload();
    $this->signInToStudio($actor);
    foreach ([1, 2] as $attempt) {
        $this->post('http://lotus.yoga.test/studio/availability', $data)->assertRedirect();
        app(TenantContext::class)->setTenant($actor->tenant);
    }
    $this->postJson('http://lotus.yoga.test/studio/availability', [...$data, 'duration_minutes' => 90])->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $this->assertDatabaseCount('availability_rules', 1);
    $this->assertDatabaseCount('tenant_audit_logs', 1);
});

test('availability gate permits only active owners and managers', function (string $role, bool $allowed) {
    $actor = availabilityActor($role);
    expect(Gate::forUser($actor)->allows('studio-catalog'))->toBe($allowed);
})->with([['owner', true], ['manager', true], ['instructor', false], ['customer', false]]);

test('availability endpoints reject an instructor and guests', function () {
    $actor = availabilityActor('instructor');
    $rule = AvailabilityRule::factory()->create();
    $this->getJson('http://lotus.yoga.test/studio/availability')->assertUnauthorized();
    $this->signInToStudio($actor);
    $this->get('http://lotus.yoga.test/studio/availability')->assertForbidden();
    $this->postJson('http://lotus.yoga.test/studio/availability', [])->assertForbidden();
    $this->patchJson("http://lotus.yoga.test/studio/availability/{$rule->id}", [])->assertForbidden();
    $this->postJson("http://lotus.yoga.test/studio/availability/{$rule->id}/exceptions", [])->assertForbidden();
    $this->postJson("http://lotus.yoga.test/studio/availability/{$rule->id}/close", [])->assertForbidden();
    $this->assertDatabaseCount('tenant_audit_logs', 0);
});

test('availability rejects foreign bindings resources and client tenant context', function () {
    $actor = availabilityActor();
    $data = availabilityPayload();
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    $foreign = AvailabilityRule::factory()->create();
    $this->signInToStudio($actor);
    $this->patchJson("http://lotus.yoga.test/studio/availability/{$foreign->id}", $data)->assertNotFound();
    $this->postJson("http://lotus.yoga.test/studio/availability/{$foreign->id}/exceptions", [])->assertNotFound();
    $this->postJson("http://lotus.yoga.test/studio/availability/{$foreign->id}/close", [])->assertNotFound();
    foreach (['training_type_id', 'instructor_profile_id', 'room_id'] as $field) {
        $this->postJson('http://lotus.yoga.test/studio/availability', [...$data, $field => $foreign->$field])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->postJson('http://lotus.yoga.test/studio/availability', [...$data, 'tenant_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('tenant_id');
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->get('http://lotus.yoga.test/studio/availability?date=2026-10-12')->assertOk()->assertInertia(fn (Assert $page) => $page->has('availability', 0)->has('preview.slots', 0));
    $this->assertDatabaseCount('tenant_audit_logs', 0);
});

test('availability rejects invalid window configuration', function (array $invalid, string $field) {
    $actor = availabilityActor();
    $data = availabilityPayload();
    $this->signInToStudio($actor);
    $this->postJson('http://lotus.yoga.test/studio/availability', [...$data, ...$invalid])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('availability_rules', 0);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
})->with([
    [['weekday' => 0], 'weekday'], [['duration_minutes' => 0], 'duration_minutes'], [['slot_step_minutes' => 0], 'slot_step_minutes'],
    [['buffer_before_minutes' => -1], 'buffer_before_minutes'], [['buffer_after_minutes' => 241], 'buffer_after_minutes'],
    [['local_end_time' => '08:00'], 'local_end_time'], [['local_end_time' => '10:00'], 'local_end_time'],
    [['ends_on' => '2026-10-11'], 'ends_on'], [['timezone' => 'UTC'], 'timezone'], [['booking_rule_version_id' => 1], 'booking_rule_version_id'],
]);

test('inactive resources and group training are not private availability', function (string $resource) {
    $actor = availabilityActor();
    $data = availabilityPayload();
    if ($resource === 'training_type_id') {
        TrainingType::query()->findOrFail($data[$resource])->forceFill(['mode' => 'group'])->save();
    } elseif ($resource === 'instructor_profile_id') {
        InstructorProfile::query()->findOrFail($data[$resource])->user->forceFill(['is_active' => false])->save();
    } else {
        Room::query()->findOrFail($data[$resource])->forceFill(['archived_at' => now()])->save();
    }
    $this->signInToStudio($actor);
    $this->postJson('http://lotus.yoga.test/studio/availability', $data)->assertUnprocessable()->assertJsonValidationErrors($resource);
})->with(['training_type_id', 'instructor_profile_id', 'room_id']);

test('slots subtract both kinds of sessions on either resource including buffers and allow equal edges', function (string $resource, string $mode, string $status, array $expected) {
    availabilityActor();
    $rule = AvailabilityRule::factory()->create(['local_end_time' => '13:00']);
    availabilitySession($rule, [
        'training_type_id' => $mode === 'private' ? $rule->training_type_id : TrainingType::factory()->create()->id,
        'instructor_profile_id' => $resource === 'instructor' ? $rule->instructor_profile_id : InstructorProfile::factory()->create()->id,
        'room_id' => $resource === 'room' ? $rule->room_id : Room::factory()->create()->id,
        'status' => $status,
    ]);

    $result = app(CalculatePrivateSlots::class)->handle('2026-10-12');

    expect(array_column($result['slots'], 'local_start'))->toBe($expected);
    expect($result['warnings'])->toBe([]);
})->with([
    ['instructor', 'group', 'published', ['11:30']],
    ['room', 'private', 'published', ['11:30']],
    ['room', 'group', 'draft', ['11:30']],
    ['instructor', 'private', 'cancelled', ['09:30', '10:00', '10:30', '11:00', '11:30']],
]);

test('notice and horizon are inclusive at exact instants', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 07:30:00', 'UTC'));
    availabilityActor();
    $rule = AvailabilityRule::factory()->create(['local_end_time' => '13:00']);
    $rule->ruleVersion->forceFill(['minimum_notice_minutes' => 120, 'booking_horizon_days' => 7])->save();

    expect(array_column(app(CalculatePrivateSlots::class)->handle('2026-10-12')['slots'], 'local_start'))->toBe(['11:30']);
    expect(array_column(app(CalculatePrivateSlots::class)->handle('2026-10-19')['slots'], 'local_start'))->toBe(['09:30']);
    expect(app(CalculatePrivateSlots::class)->handle('2026-10-26')['slots'])->toBe([]);
});

test('exceptions close a day or add a replacement window outside the weekly weekday', function () {
    $actor = availabilityActor();
    $rule = AvailabilityRule::factory()->create(['local_end_time' => '13:00']);
    $this->signInToStudio($actor);
    $payload = ['local_date' => '2026-10-12', 'is_unavailable' => 1, 'local_start_time' => null, 'local_end_time' => null, 'reason' => 'Praznik', 'operation_key' => (string) Str::uuid(), 'revision' => app(ManageStudioAvailability::class)->revision($rule->fresh())];
    $url = "http://lotus.yoga.test/studio/availability/{$rule->id}/exceptions";
    $this->post($url, $payload)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    $this->post($url, $payload)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    expect(app(CalculatePrivateSlots::class)->handle('2026-10-12')['slots'])->toBe([]);
    $payload = [...$payload, 'local_date' => '2026-10-13', 'is_unavailable' => 0, 'local_start_time' => '14:00', 'local_end_time' => '16:00', 'operation_key' => (string) Str::uuid(), 'revision' => app(ManageStudioAvailability::class)->revision($rule->fresh())];
    $this->post($url, $payload)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    expect(array_column(app(CalculatePrivateSlots::class)->handle('2026-10-13')['slots'], 'local_start'))->toBe(['14:30']);
    $this->assertDatabaseCount('availability_exceptions', 2);
    $this->assertDatabaseCount('tenant_audit_logs', 2);
});

test('availability changes cannot invalidate a concrete private appointment and roll back audit', function (string $operation) {
    $actor = availabilityActor();
    $rule = AvailabilityRule::factory()->create();
    $session = availabilitySession($rule);
    $before = $session->getAttributes();
    $payload = availabilityPayload($rule);
    $this->signInToStudio($actor);
    $base = "http://lotus.yoga.test/studio/availability/{$rule->id}";
    if ($operation === 'save') {
        $this->patchJson($base, [...$payload, 'local_start_time' => '11:00'])->assertUnprocessable()->assertJsonValidationErrors('availability');
    } elseif ($operation === 'exception') {
        $this->postJson($base.'/exceptions', ['operation_key' => $payload['operation_key'], 'revision' => $payload['revision'], 'local_date' => '2026-10-12', 'is_unavailable' => 1, 'reason' => 'Odsutnost'])->assertUnprocessable()->assertJsonValidationErrors('availability');
    } else {
        $this->postJson($base.'/close', ['operation_key' => $payload['operation_key'], 'revision' => $payload['revision']])->assertUnprocessable()->assertJsonValidationErrors('availability');
    }
    app(TenantContext::class)->setTenant($actor->tenant);
    expect($session->fresh()->getAttributes())->toBe($before);
    expect($rule->fresh()->archived_at)->toBeNull();
    $this->assertDatabaseCount('availability_exceptions', 0);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
})->with(['save', 'exception', 'close']);

test('compatible availability expansion preserves appointments and rejects stale edits', function () {
    $actor = availabilityActor();
    $rule = AvailabilityRule::factory()->create(['local_end_time' => '13:00']);
    $session = availabilitySession($rule);
    $before = $session->getAttributes();
    $data = [...availabilityPayload($rule), 'local_end_time' => '14:00'];
    $this->signInToStudio($actor);
    $url = "http://lotus.yoga.test/studio/availability/{$rule->id}";
    $this->patch($url, $data)->assertRedirect();
    $this->patch($url, $data)->assertRedirect();
    $this->patchJson($url, [...$data, 'operation_key' => (string) Str::uuid(), 'local_end_time' => '15:00'])->assertUnprocessable()->assertJsonValidationErrors('revision');
    app(TenantContext::class)->setTenant($actor->tenant);
    expect($session->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('tenant_audit_logs', 1);
});

test('closing availability is idempotent and preserves history', function () {
    $actor = availabilityActor();
    $rule = AvailabilityRule::factory()->create();
    $session = availabilitySession($rule, ['status' => 'cancelled']);
    $this->signInToStudio($actor);
    $data = ['operation_key' => (string) Str::uuid(), 'revision' => app(ManageStudioAvailability::class)->revision($rule->fresh())];
    $url = "http://lotus.yoga.test/studio/availability/{$rule->id}/close";
    $this->post($url, $data)->assertRedirect();
    $this->post($url, $data)->assertRedirect();
    app(TenantContext::class)->setTenant($actor->tenant);
    expect($rule->fresh()->archived_at)->not->toBeNull();
    expect(app(CalculatePrivateSlots::class)->handle('2026-10-12')['slots'])->toBe([]);
    $this->assertModelExists($session);
    $this->assertDatabaseCount('tenant_audit_logs', 1);
});

test('ambiguous or missing DST times suppress the window until an explicit exception', function (string $today, string $date) {
    $this->travelTo(CarbonImmutable::parse($today, 'UTC'));
    availabilityActor();
    $rule = AvailabilityRule::factory()->create(['weekday' => 7, 'starts_on' => $date, 'local_start_time' => '01:00', 'local_end_time' => '05:00', 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0]);
    $result = app(CalculatePrivateSlots::class)->handle($date);
    expect($result['slots'])->toBe([]);
    expect($result['warnings'])->toHaveCount(1);
    AvailabilityException::factory()->create(['availability_rule_id' => $rule->id, 'local_date' => $date, 'is_unavailable' => false, 'local_start_time' => '03:30', 'local_end_time' => '05:00']);
    $result = app(CalculatePrivateSlots::class)->handle($date);
    expect(array_column($result['slots'], 'local_start'))->toBe(['03:30', '04:00']);
    expect($result['warnings'])->toBe([]);
})->with([['2026-03-20', '2026-03-29'], ['2026-10-20', '2026-10-25']]);

test('private weekly slots keep local time across DST changes', function (string $today, string $date, string $expected) {
    $this->travelTo(CarbonImmutable::parse($today, 'UTC'));
    availabilityActor();
    AvailabilityRule::factory()->create(['starts_on' => '2026-01-01', 'local_end_time' => '11:00']);

    expect(app(CalculatePrivateSlots::class)->handle($date)['slots'][0]['starts_at'])->toBe($expected);
})->with([
    ['2026-03-20', '2026-03-23', '2026-03-23T08:30:00+00:00'], ['2026-03-20', '2026-03-30', '2026-03-30T07:30:00+00:00'],
    ['2026-10-15', '2026-10-19', '2026-10-19T07:30:00+00:00'], ['2026-10-15', '2026-10-26', '2026-10-26T08:30:00+00:00'],
]);

test('availability mutation takes tenant coordination lock before write', function () {
    $actor = availabilityActor();
    $data = availabilityPayload();
    DB::enableQueryLog();
    app(ManageStudioAvailability::class)->save($actor, new AvailabilityRule, $data);
    $queries = collect(DB::getQueryLog())->pluck('query')->all();
    DB::disableQueryLog();
    $lock = array_find_key($queries, fn (string $sql): bool => str_contains($sql, '`tenants`') && str_contains($sql, 'for update'));
    $write = array_find_key($queries, fn (string $sql): bool => str_contains($sql, 'insert into `availability_rules`'));

    expect(DB::getDriverName())->toBe('mysql');
    expect($lock)->toBeInt()->toBeLessThan($write);
});

test('editing an exception can clear its hours and remains one dated exception', function () {
    $actor = availabilityActor();
    $rule = AvailabilityRule::factory()->create();
    AvailabilityException::factory()->create(['availability_rule_id' => $rule->id, 'local_date' => '2026-10-12', 'is_unavailable' => false, 'local_start_time' => '10:00', 'local_end_time' => '14:00']);
    $data = ['local_date' => '2026-10-12', 'is_unavailable' => 1, 'reason' => 'Zatvoreno', 'operation_key' => (string) Str::uuid(), 'revision' => app(ManageStudioAvailability::class)->revision($rule->fresh())];
    $this->signInToStudio($actor);

    $this->post("http://lotus.yoga.test/studio/availability/{$rule->id}/exceptions", $data)->assertRedirect();

    $this->assertDatabaseHas('availability_exceptions', ['availability_rule_id' => $rule->id, 'is_unavailable' => 1, 'local_start_time' => null, 'local_end_time' => null, 'reason' => 'Zatvoreno']);
    $this->assertDatabaseCount('availability_exceptions', 1);
});

test('exception rejects invalid dates and inconsistent hours', function (array $invalid, string $field) {
    $actor = availabilityActor();
    $rule = AvailabilityRule::factory()->create(['ends_on' => '2026-10-31']);
    $data = ['local_date' => '2026-10-12', 'is_unavailable' => 0, 'local_start_time' => '09:00', 'local_end_time' => '13:00', 'reason' => 'Promjena', 'operation_key' => (string) Str::uuid(), 'revision' => app(ManageStudioAvailability::class)->revision($rule->fresh())];
    $this->signInToStudio($actor);

    $this->postJson("http://lotus.yoga.test/studio/availability/{$rule->id}/exceptions", [...$data, ...$invalid])->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('availability_exceptions', 0);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
})->with([
    [['local_date' => '2026-10-11'], 'local_date'], [['local_date' => '2026-11-01'], 'local_date'],
    [['local_end_time' => '08:00'], 'local_end_time'], [['is_unavailable' => 1], 'local_start_time'], [['reason' => ''], 'reason'],
]);

test('slots respect availability dates and ignore foreign occupancy', function () {
    $actor = availabilityActor();
    $rule = AvailabilityRule::factory()->create(['ends_on' => '2026-10-19', 'local_end_time' => '11:00']);
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    ClassSession::factory()->create(['starts_at' => '2026-10-12 07:00:00', 'ends_at' => '2026-10-12 15:00:00']);
    app(TenantContext::class)->setTenant($actor->tenant);

    expect(array_column(app(CalculatePrivateSlots::class)->handle('2026-10-12')['slots'], 'local_start'))->toBe(['09:30']);
    expect(array_column(app(CalculatePrivateSlots::class)->handle('2026-10-19')['slots'], 'local_start'))->toBe(['09:30']);
    expect(app(CalculatePrivateSlots::class)->handle('2026-10-11')['slots'])->toBe([]);
    expect(app(CalculatePrivateSlots::class)->handle('2026-10-26')['slots'])->toBe([]);
    $rule->instructor->user->forceFill(['is_active' => false])->save();
    expect(app(CalculatePrivateSlots::class)->handle('2026-10-12')['slots'])->toBe([]);
});

test('slot calculation fails closed without tenant context', function () {
    app(TenantContext::class)->clear();

    expect(fn () => app(CalculatePrivateSlots::class)->handle('2026-10-12'))->toThrow(LogicException::class);
});

test('missing owner rules prevent availability creation', function () {
    $actor = availabilityActor();
    $data = availabilityPayload();
    BookingRuleVersion::query()->delete();
    $this->signInToStudio($actor);

    $this->postJson('http://lotus.yoga.test/studio/availability', $data)->assertUnprocessable()->assertJsonValidationErrors('rules');

    $this->assertDatabaseCount('availability_rules', 0);
});
