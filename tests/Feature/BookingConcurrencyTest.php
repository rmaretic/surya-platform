<?php

use App\Actions\CreateBooking;
use App\Actions\ManageStudioCancellation;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/../BookingFixtures.php';
require_once __DIR__.'/../BookingRace.php';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
});

function bookingOperation(User $actor, array $data): array
{
    return ['tenant_id' => $actor->tenant_id, 'actor_id' => $actor->id, 'data' => ['operation_key' => (string) Str::uuid(), ...$data]];
}

test('booking racing studio cancellation either rejects stale preview or rejects booking', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $manager = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'manager']);
    $data = ['operation_key' => (string) Str::uuid(), 'reason' => 'Otkaz studija',
        'preview_token' => app(ManageStudioCancellation::class)->preview($manager, $session)['preview_token']];
    $results = bookingRace([
        bookingOperation($customer, ['class_session_id' => $session->id]),
        ['tenant_id' => $tenant->id, 'actor_id' => $manager->id, 'action' => 'studio_cancel', 'session_id' => $session->id, 'data' => $data],
    ], [$tenant->id]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing(['ok', 'rejected']);
    if ($results[1]['status'] === 'rejected') {
        expect($results[1]['errors'])->toHaveKey('preview_token');
        app(ManageStudioCancellation::class)->cancel($manager, $session, [...$data,
            'preview_token' => app(ManageStudioCancellation::class)->preview($manager, $session)['preview_token']]);
    }
    expect($session->fresh()->status)->toBe('cancelled');
    expect($session->bookings()->where('status', 'confirmed')->count())->toBe(0);
    expect((int) $grant->entries()->sum('amount'))->toBe(1);
    expect(DB::table('outbox_events')->where('event_type', 'session.cancelled')->count())->toBe($results[0]['status'] === 'ok' ? 1 : 0);
});

test('concurrent studio cancellation retries return each credit exactly once', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $manager = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'manager']);
    $operation = ['tenant_id' => $tenant->id, 'actor_id' => $manager->id, 'action' => 'studio_cancel', 'session_id' => $session->id,
        'data' => ['operation_key' => (string) Str::uuid(), 'reason' => 'Otkaz',
            'preview_token' => app(ManageStudioCancellation::class)->preview($manager, $session)['preview_token']]];
    expect(array_column(bookingRace([$operation, $operation], [$tenant->id]), 'status'))->toBe(['ok', 'ok']);
    expect((int) $grant->entries()->sum('amount'))->toBe(1);
    $this->assertDatabaseCount('credit_entries', 3);
    $this->assertDatabaseCount('outbox_events', 2);
});

test('concurrent owner exceptions cannot duplicate compensation even with different keys', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $grant->forceFill(['expires_at' => '2026-10-13 10:00:00'])->save();
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $operation = ['tenant_id' => $tenant->id, 'actor_id' => $owner->id, 'action' => 'refund_exception', 'booking_id' => $booking->id,
        'data' => ['operation_key' => (string) Str::uuid(), 'reason' => 'Iznimka']];
    $second = [...$operation, 'data' => [...$operation['data'], 'operation_key' => (string) Str::uuid()]];
    expect(array_column(bookingRace([$operation, $second], [$tenant->id]), 'status'))->toEqualCanonicalizing(['ok', 'rejected']);
    $this->assertDatabaseCount('credit_grants', 2);
    $this->assertDatabaseCount('credit_entries', 5);
    $this->assertDatabaseCount('outbox_events', 2);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
});

test('two processes competing for the last place confirm only one booking and debit', function () {
    [$tenant, $first, $session] = bookingSetup();
    $session->forceFill(['capacity' => 1])->save();
    $second = User::factory()->for($tenant)->create();
    bookingGrant($second, $session->training_type_id);
    $results = bookingRace([
        bookingOperation($first, ['class_session_id' => $session->id]),
        bookingOperation($second, ['class_session_id' => $session->id]),
    ], [$tenant->id]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing(['ok', 'rejected']);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('credit_entries', 3);
    $this->assertDatabaseCount('outbox_events', 1);
});

test('two processes competing for the last credit cannot confirm different sessions', function () {
    [$tenant, $customer, $first, $grant] = bookingSetup();
    $second = ClassSession::factory()->for($first->trainingType)->create(['status' => 'published', 'starts_at' => '2026-10-19 12:00:00', 'ends_at' => '2026-10-19 13:00:00']);
    $results = bookingRace([
        bookingOperation($customer, ['class_session_id' => $first->id]),
        bookingOperation($customer, ['class_session_id' => $second->id]),
    ], [$tenant->id]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing(['ok', 'rejected']);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('credit_entries', 2);
});

test('two new private intervals cannot overlap instructor or room including buffers', function (string $resource) {
    [$tenant, $first] = bookingSetup();
    $second = User::factory()->for($tenant)->create();
    $firstRule = bookingPrivate($first);
    $secondRule = bookingPrivate($second);
    $secondRule->forceFill([$resource => $firstRule->getAttribute($resource)])->save();
    $results = bookingRace([
        bookingOperation($first, ['availability_rule_id' => $firstRule->id, 'starts_at' => '2026-10-19T10:00:00+00:00']),
        bookingOperation($second, ['availability_rule_id' => $secondRule->id, 'starts_at' => '2026-10-19T11:00:00+00:00']),
    ], [$tenant->id]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing(['ok', 'rejected']);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('class_sessions', 2);
    $this->assertDatabaseCount('credit_entries', 4);
})->with(['instructor_profile_id', 'room_id']);

test('new group schedule and private booking cannot claim the same resource interval', function () {
    [$tenant, $customer, $group] = bookingSetup();
    $rule = bookingPrivate($customer);
    $manager = User::factory()->for($tenant)->create(['role' => 'manager']);
    $schedule = [
        'training_type_id' => $group->training_type_id, 'instructor_profile_id' => $rule->instructor_profile_id,
        'room_id' => $rule->room_id, 'duration_minutes' => 60, 'capacity' => 1,
        'local_start' => '2026-10-19 12:00', 'status' => 'published',
    ];
    $results = bookingRace([
        bookingOperation($customer, ['availability_rule_id' => $rule->id, 'starts_at' => '2026-10-19T10:00:00+00:00']),
        ['tenant_id' => $tenant->id, 'actor_id' => $manager->id, 'action' => 'schedule', 'data' => $schedule],
    ], [$tenant->id]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing(['ok', 'rejected']);
    $this->assertDatabaseCount('class_sessions', 2);
    $expectedBookings = $results[0]['status'] === 'ok' ? 1 : 0;
    $this->assertDatabaseCount('bookings', $expectedBookings);
    $this->assertDatabaseCount('credit_entries', 2 + $expectedBookings);
    $this->assertDatabaseCount('outbox_events', $expectedBookings);
});

test('simultaneous retries return the same booking with one debit and outbox', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $operation = bookingOperation($customer, ['class_session_id' => $session->id]);
    $results = bookingRace([$operation, $operation], [$tenant->id]);
    expect(array_column($results, 'status'))->toBe(['ok', 'ok']);
    expect($results[0]['booking_id'])->toBe($results[1]['booking_id']);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseCount('outbox_events', 1);
});

test('simultaneous bookings cannot overlap the customer even with separate resources and credits', function () {
    [$tenant, $customer, $first] = bookingSetup();
    bookingGrant($customer, $first->training_type_id);
    $second = ClassSession::factory()->for($first->trainingType)->create(['status' => 'published', 'starts_at' => '2026-10-19 10:30:00', 'ends_at' => '2026-10-19 11:30:00']);
    $results = bookingRace([
        bookingOperation($customer, ['class_session_id' => $first->id]),
        bookingOperation($customer, ['class_session_id' => $second->id]),
    ], [$tenant->id]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing(['ok', 'rejected']);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('credit_entries', 3);
});

test('another studio commits while the first studio coordination row is locked', function () {
    [$firstTenant, $first, $firstSession] = bookingSetup();
    [$secondTenant, $second, $secondSession] = bookingSetup();
    $results = bookingRace([
        bookingOperation($first, ['class_session_id' => $firstSession->id]),
        bookingOperation($second, ['class_session_id' => $secondSession->id]),
    ], [$firstTenant->id], independentWorker: 1);
    expect(array_column($results, 'status'))->toBe(['ok', 'ok']);
    $this->assertDatabaseCount('bookings', 2);
    $this->assertDatabaseCount('credit_entries', 4);
});

test('booking racing a capacity reduction preserves the confirmed capacity invariant', function () {
    [$tenant, $first, $session] = bookingSetup();
    $session->forceFill(['capacity' => 2])->save();
    app(CreateBooking::class)->handle($first, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $second = User::factory()->for($tenant)->create();
    bookingGrant($second, $session->training_type_id);
    $manager = User::factory()->for($tenant)->create(['role' => 'manager']);
    bookingRace([
        bookingOperation($second, ['class_session_id' => $session->id]),
        ['tenant_id' => $tenant->id, 'actor_id' => $manager->id, 'action' => 'schedule', 'session_id' => $session->id, 'data' => [
            ...$session->only(['training_type_id', 'instructor_profile_id', 'room_id']),
            'capacity' => 1, 'duration_minutes' => 60, 'status' => 'published', 'local_start' => '2026-10-19 12:00',
        ]],
    ], [$tenant->id]);
    expect(Booking::where('class_session_id', $session->id)->count())->toBe($session->fresh()->capacity);
    expect(DB::table('credit_entries')->where('kind', 'debit')->count())->toBe(Booking::count());
});

test('simultaneous cancellations reverse the debit only once', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $operations = [];
    for ($i = 0; $i < 2; $i++) {
        $operations[] = [...bookingOperation($customer, []), 'action' => 'cancel', 'booking_id' => $booking->id];
    }
    $results = bookingRace($operations, [$tenant->id]);
    expect(array_column($results, 'status'))->toBe(['ok', 'ok']);
    expect((int) $grant->entries()->sum('amount'))->toBe(1);
    $this->assertDatabaseCount('credit_entries', 3);
    $this->assertDatabaseCount('outbox_events', 2);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
});

test('simultaneous moves cannot transfer one booking twice', function (bool $sameOperation) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $target = ClassSession::factory()->for($session->trainingType)->create(['status' => 'published', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);
    $operation = [...bookingOperation($customer, ['class_session_id' => $target->id]), 'action' => 'move', 'booking_id' => $booking->id];
    $second = $sameOperation ? $operation : [...$operation, 'data' => [...$operation['data'], 'operation_key' => (string) Str::uuid()]];
    $results = bookingRace([$operation, $second], [$tenant->id]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing($sameOperation ? ['ok', 'ok'] : ['ok', 'rejected']);
    expect(Booking::where('status', 'confirmed')->count())->toBe(1);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
    $this->assertDatabaseCount('bookings', 2);
    $this->assertDatabaseCount('credit_entries', 4);
    $this->assertDatabaseCount('outbox_events', 3);
})->with([true, false]);

test('move competing for the last target place either commits wholly or retains the old booking', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $target = ClassSession::factory()->for($session->trainingType)->create(['status' => 'published', 'capacity' => 1, 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);
    $other = User::factory()->for($tenant)->create();
    bookingGrant($other, $target->training_type_id);
    $results = bookingRace([
        [...bookingOperation($customer, ['class_session_id' => $target->id]), 'action' => 'move', 'booking_id' => $booking->id],
        bookingOperation($other, ['class_session_id' => $target->id]),
    ], [$tenant->id]);
    expect(array_column($results, 'status'))->toEqualCanonicalizing(['ok', 'rejected']);
    expect($target->bookings()->where('status', 'confirmed')->count())->toBe(1);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => $results[0]['status'] === 'ok' ? 'cancelled' : 'confirmed']);
    expect($booking->entries()->where('kind', 'reversal')->count())->toBe($results[0]['status'] === 'ok' ? 1 : 0);
});
