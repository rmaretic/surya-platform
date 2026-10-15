<?php

use App\Actions\CreateBooking;
use App\Actions\CreditLedger;
use App\Actions\ManageBooking;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../BookingFixtures.php';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
});

function managedBooking(User $customer, ClassSession $session): Booking
{
    return app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
}

function moveDestination(ClassSession $session, array $overrides = []): ClassSession
{
    return ClassSession::factory()->create([
        ...$session->only(['training_type_id', 'instructor_profile_id', 'room_id', 'booking_rule_version_id']),
        'status' => 'published', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00', ...$overrides,
    ]);
}

test('cancellation preview and exact deadline use the booking snapshot', function (string $at, bool $refund) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $session->ruleVersion->forceFill(['group_cancellation_minutes' => 2880])->save();
    $this->travelTo(CarbonImmutable::parse($at, 'UTC'));
    $this->signInToStudio($customer);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id;

    $this->getJson($url.'/cancellation')->assertOk()->assertJsonPath('cancellation.returns_credit', $refund)
        ->assertJsonPath('cancellation.can_move', $refund)->assertJsonPath('cancellation.cancellation_deadline', '2026-10-18T22:00:00+00:00');
    $this->postJson($url.'/cancel', ['operation_key' => (string) Str::uuid()])->assertOk()->assertJsonPath('booking.status', 'cancelled');

    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'cancelled_by_user_id' => $customer->id,
        'cancellation_reason' => $refund ? 'customer_timely' : 'customer_late']);
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'published']);
    $this->assertDatabaseCount('credit_entries', $refund ? 3 : 2);
    $this->assertDatabaseHas('outbox_events', ['booking_id' => $booking->id, 'event_type' => 'booking.cancelled']);
    app(TenantContext::class)->setTenant($tenant);
    expect((int) $grant->entries()->sum('amount'))->toBe($refund ? 1 : 0);
})->with([
    ['2026-10-18 21:59:59', true], ['2026-10-18 22:00:00', true], ['2026-10-18 22:00:01', false], ['2026-10-19 09:59:59', false],
]);

test('duplicate cancellation returns one credit and rebooking creates new history', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $this->signInToStudio($customer);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id.'/cancel';
    $data = ['operation_key' => (string) Str::uuid()];
    $this->postJson($url, $data)->assertOk();
    $this->postJson($url, $data)->assertOk();
    $this->postJson($url, ['operation_key' => (string) Str::uuid()])->assertOk();
    $this->assertDatabaseCount('credit_entries', 3);
    $this->assertDatabaseCount('outbox_events', 2);
    app(TenantContext::class)->setTenant($tenant);
    $new = managedBooking($customer, $session);
    expect($new->id)->not->toBe($booking->id);
    expect(Booking::where('status', 'confirmed')->count())->toBe(1);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
});

test('standard cancel and move reject at or after start even for owner', function (string $at, string $role) {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $target = moveDestination($session);
    $actor = $role === 'customer' ? $customer : User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $this->travelTo(CarbonImmutable::parse($at, 'UTC'));
    $this->signInToStudio($actor);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id;
    $this->getJson($url.'/cancellation')->assertOk()->assertJsonPath('cancellation.can_cancel', false);
    $this->postJson($url.'/cancel', ['operation_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('booking');
    $this->postJson($url.'/move', ['operation_key' => (string) Str::uuid(), 'class_session_id' => $target->id])->assertUnprocessable();
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseCount('outbox_events', 1);
})->with(['2026-10-19 10:00:00', '2026-10-19 10:00:01'])->with(['customer', 'owner']);

test('move transfers the original grant and snapshot atomically with replayable history', function (string $role) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $otherGrant = bookingGrant($customer, $session->training_type_id, 1, ['expires_at' => '2026-10-22 12:00:00']);
    $target = moveDestination($session);
    $actor = $role === 'customer' ? $customer : User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
    $this->signInToStudio($actor);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id.'/move';
    $data = ['operation_key' => (string) Str::uuid(), 'class_session_id' => $target->id];
    $id = $this->postJson($url, $data)->assertOk()->assertJsonPath('booking.status', 'confirmed')->json('booking.id');
    $this->travel(90)->days();
    $this->signInToStudio($actor);
    $this->postJson($url, $data)->assertOk()->assertJsonPath('booking.id', $id);
    $this->postJson($url, [...$data, 'class_session_id' => $session->id])->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled', 'cancellation_reason' => 'moved']);
    $this->assertDatabaseHas('bookings', ['id' => $id, 'credit_grant_id' => $grant->id, 'class_session_id' => $target->id]);
    $this->assertDatabaseCount('bookings', 2);
    $this->assertDatabaseCount('outbox_events', 3);
    app(TenantContext::class)->setTenant($tenant);
    expect(Booking::findOrFail($id)->rules_snapshot)->toEqual($booking->rules_snapshot);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
    expect((int) $otherGrant->entries()->sum('amount'))->toBe(1);
})->with(['customer', 'owner', 'manager']);

test('unavailable or incompatible destination leaves old booking ledger audit and outbox intact', function (string $failure) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $target = moveDestination($session);
    if ($failure === 'full') {
        $target->forceFill(['capacity' => 1])->save();
        $other = User::factory()->for($tenant)->create();
        bookingGrant($other, $target->training_type_id);
        managedBooking($other, $target);
    } elseif ($failure === 'expired') {
        $grant->forceFill(['expires_at' => $target->starts_at])->save();
        bookingGrant($customer, $target->training_type_id);
    } elseif ($failure === 'type') {
        $target = ClassSession::factory()->create(['status' => 'published', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);
        $grant->trainingTypes()->attach($target->training_type_id);
    } elseif ($failure === 'draft') {
        $target->forceFill(['status' => 'draft'])->save();
    } elseif ($failure === 'same') {
        $target = $session;
    } elseif ($failure === 'late') {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 22:00:01', 'UTC'));
    } elseif ($failure === 'duration') {
        $target->forceFill(['ends_at' => '2026-10-20 12:00:00'])->save();
    } elseif ($failure === 'overlap') {
        $other = ClassSession::factory()->create(['status' => 'published', 'starts_at' => '2026-10-20 10:30:00', 'ends_at' => '2026-10-20 11:30:00']);
        bookingGrant($customer, $other->training_type_id);
        managedBooking($customer, $other);
    }
    $before = [];
    foreach (['bookings', 'credit_entries', 'tenant_audit_logs', 'outbox_events'] as $table) {
        $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }
    $this->signInToStudio($customer);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id.'/move', [
        'operation_key' => (string) Str::uuid(), 'class_session_id' => $target->id,
    ])->assertUnprocessable();
    foreach ($before as $table => $rows) {
        expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($rows);
    }
})->with(['full', 'expired', 'type', 'draft', 'same', 'late', 'duration', 'overlap']);

test('private cancellation uses 24 hour deadline and releases the occupied resource interval', function (string $at, bool $refund) {
    [$tenant, $customer] = bookingSetup();
    $rule = bookingPrivate($customer);
    $booking = app(CreateBooking::class)->handle($customer, [
        'availability_rule_id' => $rule->id, 'starts_at' => '2026-10-19T10:00:00+00:00', 'operation_key' => (string) Str::uuid(),
    ]);
    $this->travelTo(CarbonImmutable::parse($at, 'UTC'));
    expect(app(ManageBooking::class)->preview($customer, $booking)['returns_credit'])->toBe($refund);
    app(ManageBooking::class)->handle($customer, $booking, ['operation_key' => (string) Str::uuid()]);
    $this->assertDatabaseHas('class_sessions', ['id' => $booking->class_session_id, 'status' => 'cancelled']);
    expect($booking->entries()->where('kind', 'reversal')->count())->toBe($refund ? 1 : 0);
})->with([['2026-10-18 09:59:59', true], ['2026-10-18 10:00:00', true], ['2026-10-18 10:00:01', false]]);

test('private move can reuse the old resource interval and failed slot restores it', function () {
    [$tenant, $customer] = bookingSetup();
    $rule = bookingPrivate($customer);
    $booking = app(CreateBooking::class)->handle($customer, [
        'availability_rule_id' => $rule->id, 'starts_at' => '2026-10-19T10:00:00+00:00', 'operation_key' => (string) Str::uuid(),
    ]);
    $this->signInToStudio($customer);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id.'/move';
    $data = ['availability_rule_id' => $rule->id, 'operation_key' => (string) Str::uuid()];
    $this->postJson($url, [...$data, 'starts_at' => '2026-10-19T03:00:00+00:00'])->assertUnprocessable();
    $this->assertDatabaseHas('class_sessions', ['id' => $booking->class_session_id, 'status' => 'published']);
    $this->assertDatabaseCount('bookings', 1);
    $new = $this->postJson($url, [...$data, 'starts_at' => '2026-10-19T10:30:00+00:00'])->assertOk()->json('booking.id');
    $this->assertDatabaseHas('bookings', ['id' => $new, 'credit_grant_id' => $booking->credit_grant_id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('class_sessions', ['id' => $booking->class_session_id, 'status' => 'cancelled']);
    $this->assertDatabaseCount('class_sessions', 3);
});

test('unauthorized users cannot preview cancel or move another customer booking', function (string $role) {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $actor = User::factory()->for($tenant)->create(['role' => $role]);
    $this->signInToStudio($actor);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id;
    $this->getJson($url.'/cancellation')->assertForbidden();
    $this->postJson($url.'/cancel', ['operation_key' => (string) Str::uuid()])->assertForbidden();
    $this->postJson($url.'/move', ['operation_key' => (string) Str::uuid(), 'class_session_id' => $session->id])->assertForbidden();
    $this->assertDatabaseCount('credit_entries', 2);
})->with(['customer', 'instructor']);

test('other tenant booking and destination are hidden even with the same email', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    [$otherTenant, $otherCustomer, $otherSession] = bookingSetup();
    $otherCustomer->forceFill(['email' => $customer->email])->save();
    $otherBooking = managedBooking($otherCustomer, $otherSession);
    $this->signInToStudio($customer);
    $base = 'http://booking-'.$tenant->id.'.yoga.test/bookings/';
    $this->getJson($base.$otherBooking->id.'/cancellation')->assertNotFound();
    $this->postJson($base.$otherBooking->id.'/cancel', ['operation_key' => (string) Str::uuid()])->assertNotFound();
    $this->postJson($base.$booking->id.'/move', ['operation_key' => (string) Str::uuid(), 'class_session_id' => $otherSession->id])->assertNotFound();
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    $this->assertDatabaseCount('credit_entries', 4);
});

test('forged cancellation fields and missing operation key return 422 without writes', function (array $data, string $field) {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $this->signInToStudio($customer);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id.'/cancel', $data)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    $this->assertDatabaseCount('credit_entries', 2);
})->with([
    [[], 'operation_key'],
    [['operation_key' => 'bad'], 'operation_key'],
    [['operation_key' => '5fb30a81-6b45-4890-bb93-68080cc973d9', 'tenant_id' => 1], 'tenant_id'],
    [['operation_key' => '5fb30a81-6b45-4890-bb93-68080cc973d9', 'credit_grant_id' => 1], 'credit_grant_id'],
    [['operation_key' => '5fb30a81-6b45-4890-bb93-68080cc973d9', 'cancellation_reason' => 'owner_refund'], 'cancellation_reason'],
]);

test('move is allowed exactly at the free cancellation deadline and keeps one debit', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $target = moveDestination($session);
    $this->travelTo(CarbonImmutable::parse('2026-10-18 22:00:00', 'UTC'));
    $new = app(ManageBooking::class)->handle($customer, $booking, ['operation_key' => (string) Str::uuid(), 'class_session_id' => $target->id], true);
    expect($new->status)->toBe('confirmed');
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
    expect(Booking::where('status', 'confirmed')->count())->toBe(1);
});

test('returned expired credit retains its original expiry and is not available', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $grant->forceFill(['expires_at' => '2026-10-12 09:00:00'])->save();
    app(ManageBooking::class)->handle($customer, $booking, ['operation_key' => (string) Str::uuid()]);
    expect($grant->fresh()->expires_at->format('Y-m-d H:i:s'))->toBe('2026-10-12 09:00:00');
    expect(app(CreditLedger::class)->balance($grant))->toMatchArray(['balance' => 1, 'available' => 0, 'expired' => 1]);
});

test('deadline is an elapsed snapshot interval across the Zagreb DST change', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $session->forceFill(['starts_at' => '2026-10-25 10:00:00', 'ends_at' => '2026-10-25 11:00:00'])->save();
    $booking = managedBooking($customer, $session);
    $this->travelTo(CarbonImmutable::parse('2026-10-24 22:00:00', 'UTC'));
    expect(app(ManageBooking::class)->preview($customer, $booking))->toMatchArray([
        'returns_credit' => true, 'cancellation_deadline' => '2026-10-24T22:00:00+00:00', 'timezone' => 'Europe/Zagreb',
    ]);
});

test('cancellation operation key cannot be reused for another booking or a move', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $data = ['operation_key' => (string) Str::uuid()];
    app(ManageBooking::class)->handle($customer, $booking, $data);
    $new = managedBooking($customer, $session);
    $target = moveDestination($session);
    $this->signInToStudio($customer);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings/';
    $this->postJson($url.$new->id.'/cancel', $data)->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $this->postJson($url.$booking->id.'/move', [...$data, 'class_session_id' => $target->id])->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $this->assertDatabaseHas('bookings', ['id' => $new->id, 'status' => 'confirmed']);
    $this->assertDatabaseCount('credit_entries', 4);
});

test('guest cannot read cancellation details or mutate a booking', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id;
    $this->getJson($url.'/cancellation')->assertUnauthorized();
    $this->postJson($url.'/cancel', ['operation_key' => (string) Str::uuid()])->assertUnauthorized();
    $this->postJson($url.'/move', ['operation_key' => (string) Str::uuid(), 'class_session_id' => $session->id])->assertUnauthorized();
    $this->assertDatabaseCount('credit_entries', 2);
});

test('retry rechecks the actors current permissions', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $manager = User::factory()->for($tenant)->create(['role' => 'manager']);
    $data = ['operation_key' => (string) Str::uuid()];
    app(ManageBooking::class)->handle($manager, $booking, $data);
    $manager->forceFill(['role' => 'instructor'])->save();
    $this->signInToStudio($manager);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings/'.$booking->id.'/cancel', $data)->assertForbidden();
    $this->assertDatabaseCount('credit_entries', 3);
});

test('cancellation reloads actor activity verification and owner two factor', function (array $changes) {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    $actor = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    DB::table('users')->where('id', $actor->id)->update($changes);
    expect(fn () => app(ManageBooking::class)->handle($actor, $booking, ['operation_key' => (string) Str::uuid()]))
        ->toThrow(HttpException::class);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    $this->assertDatabaseCount('credit_entries', 2);
})->with([[['is_active' => false]], [['email_verified_at' => null]], [['two_factor_confirmed_at' => null]]]);

test('cancellation without tenant context fails closed', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = managedBooking($customer, $session);
    app(TenantContext::class)->clear();
    expect(fn () => app(ManageBooking::class)->handle($customer, $booking, ['operation_key' => (string) Str::uuid()]))->toThrow(LogicException::class);
    $this->assertDatabaseCount('credit_entries', 2);
});
