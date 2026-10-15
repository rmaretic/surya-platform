<?php

use App\Actions\CreateBooking;
use App\Models\Booking;
use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\OutboxEvent;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../BookingFixtures.php';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
});

function bookingRequest(ClassSession $session, array $overrides = []): array
{
    return ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid(), ...$overrides];
}

test('customer and administrators confirm with one debit snapshot audit and durable outbox', function (string $role) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $actor = $role === 'customer' ? $customer : User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
    $this->signInToStudio($actor);
    Mail::fake();
    Queue::fake();
    Http::preventStrayRequests();
    $data = bookingRequest($session, ['user_id' => $customer->id]);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings';
    $response = $this->postJson($url, $data)->assertOk()->assertJsonStructure(['booking' => ['id', 'class_session_id', 'status']]);
    $id = $response->json('booking.id');
    $this->postJson($url, $data)->assertExactJson(['booking' => ['id' => $id, 'class_session_id' => $session->id, 'status' => 'confirmed']]);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseHas('credit_entries', ['booking_id' => $id, 'amount' => -1, 'created_by_user_id' => $actor->id]);
    $this->assertDatabaseCount('outbox_events', 1);
    $this->assertDatabaseHas('outbox_events', ['booking_id' => $id, 'recipient_user_id' => $customer->id, 'event_type' => 'booking.confirmed', 'processed_at' => null]);
    $this->assertDatabaseHas('tenant_audit_logs', ['subject_id' => $id, 'actor_user_id' => $actor->id, 'action' => 'booking.confirmed']);
    app(TenantContext::class)->setTenant($tenant);
    expect(Booking::findOrFail($id)->rules_snapshot)->toEqual([
        'timezone' => 'Europe/Zagreb', 'booking_horizon_days' => 56, 'minimum_notice_minutes' => 120,
        'group_cancellation_minutes' => 720, 'studio_refund_validity_days' => 7, 'private_cancellation_minutes' => 1440,
    ]);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
})->with(['customer', 'owner', 'manager']);

test('private retry returns the same concrete session even after availability closes', function () {
    [$tenant, $customer] = bookingSetup();
    $rule = bookingPrivate($customer);
    $data = ['availability_rule_id' => $rule->id, 'starts_at' => '2026-10-19T12:00:00+02:00', 'operation_key' => (string) Str::uuid()];
    $booking = app(CreateBooking::class)->handle($customer, $data);
    $rule->forceFill(['archived_at' => now()])->save();
    $this->travel(60)->days();
    expect(app(CreateBooking::class)->handle($customer, [...$data, 'starts_at' => '2026-10-19T10:00:00+00:00'])->id)->toBe($booking->id);
    $this->assertDatabaseHas('class_sessions', ['id' => $booking->class_session_id, 'starts_at' => '2026-10-19 10:00:00', 'ends_at' => '2026-10-19 11:00:00', 'capacity' => 1, 'buffer_before_minutes' => 15, 'buffer_after_minutes' => 15]);
    $this->assertDatabaseCount('class_sessions', 2);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('outbox_events', 1);
});

test('operation key cannot be reused for another target or actor', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $data = bookingRequest($session);
    app(CreateBooking::class)->handle($customer, $data);
    $other = ClassSession::factory()->create();
    expect(fn () => app(CreateBooking::class)->handle($customer, [...$data, 'class_session_id' => $other->id]))->toThrow(ValidationException::class);
    $manager = User::factory()->for($tenant)->create(['role' => 'manager']);
    expect(fn () => app(CreateBooking::class)->handle($manager, [...$data, 'user_id' => $customer->id]))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('credit_entries', 2);
});

test('forged server fields and malformed intent return 422 without side effects', function (array $overrides, string $field) {
    [$tenant, $customer, $session] = bookingSetup();
    $this->signInToStudio($customer);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings', bookingRequest($session, $overrides))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 1);
    $this->assertDatabaseCount('outbox_events', 0);
})->with([
    [['tenant_id' => 1], 'tenant_id'], [['credit_grant_id' => 1], 'credit_grant_id'],
    [['status' => 'confirmed'], 'status'], [['credits_used' => 0], 'credits_used'],
    [['created_by_user_id' => 1], 'created_by_user_id'], [['rules_snapshot' => []], 'rules_snapshot'],
    [['operation_key' => 'bad'], 'operation_key'], [['class_session_id' => null], 'class_session_id'],
    [['availability_rule_id' => 1], 'availability_rule_id'], [['starts_at' => '2026-10-19T10:00:00+00:00'], 'starts_at'],
]);

test('customer cannot book another user and instructor cannot book anyone', function (string $role) {
    [$tenant, $customer, $session] = bookingSetup();
    $actor = User::factory()->for($tenant)->create(['role' => $role]);
    $this->signInToStudio($actor);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings', bookingRequest($session, ['user_id' => $customer->id]))->assertForbidden();
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 1);
})->with(['customer', 'instructor']);

test('guest platform admin unknown and unverified hosts cannot book', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings';
    $this->postJson($url, bookingRequest($session))->assertUnauthorized();
    $this->actingAs(PlatformAdmin::factory()->create(), 'platform')->postJson($url, bookingRequest($session))->assertUnauthorized();
    $this->postJson('http://unknown.yoga.test/bookings', bookingRequest($session))->assertNotFound();
    app(TenantContext::class)->setTenant($tenant);
    $tenant->domains()->update(['verified_at' => null]);
    $this->postJson($url, bookingRequest($session))->assertNotFound();
    $this->assertDatabaseCount('bookings', 0);
});

test('foreign session availability and same-email beneficiary are not found', function () {
    [$tenant, $customer, $session] = bookingSetup();
    [$foreignTenant, $foreignCustomer, $foreignSession] = bookingSetup();
    $foreignCustomer->update(['email' => $customer->email]);
    $foreignRule = bookingPrivate($foreignCustomer);
    app(TenantContext::class)->setTenant($tenant);
    $manager = User::factory()->for($tenant)->create(['role' => 'manager']);
    $this->signInToStudio($manager);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/bookings';
    $this->postJson($url, bookingRequest($foreignSession, ['user_id' => $customer->id]))->assertNotFound();
    $this->postJson($url, bookingRequest($session, ['user_id' => $foreignCustomer->id]))->assertNotFound();
    $this->postJson($url, ['availability_rule_id' => $foreignRule->id, 'starts_at' => '2026-10-19T10:00:00+00:00', 'user_id' => $customer->id, 'operation_key' => (string) Str::uuid()])->assertNotFound();
    $this->assertDatabaseCount('bookings', 0);
});

test('authorization reloads stale actors and requires active verified identity and owner two factor', function (array $changes) {
    [$tenant, $customer, $session] = bookingSetup();
    $actor = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    DB::table('users')->where('id', $actor->id)->update($changes);
    expect(fn () => app(CreateBooking::class)->handle($actor, bookingRequest($session, ['user_id' => $customer->id])))->toThrow(HttpException::class);
    $this->assertDatabaseCount('bookings', 0);
})->with([
    [['is_active' => false]], [['role' => 'instructor']], [['email_verified_at' => null]],
    [['two_factor_confirmed_at' => null]],
]);

test('inactive or staff beneficiary and suspended tenant cannot receive bookings', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $manager = User::factory()->for($tenant)->create(['role' => 'manager']);
    $customer->forceFill(['is_active' => false])->save();
    expect(fn () => app(CreateBooking::class)->handle($manager, bookingRequest($session, ['user_id' => $customer->id])))->toThrow(ValidationException::class);
    expect(fn () => app(CreateBooking::class)->handle($manager, bookingRequest($session)))->toThrow(ValidationException::class);
    $tenant->update(['status' => 'suspended']);
    expect(fn () => app(CreateBooking::class)->handle($manager, bookingRequest($session)))->toThrow(HttpException::class);
    $this->assertDatabaseCount('bookings', 0);
});

test('draft cancelled completed and unavailable resources reject booking without debit', function (string $case) {
    [$tenant, $customer, $session] = bookingSetup();
    match ($case) {
        'draft', 'completed' => $session->forceFill(['status' => $case])->save(),
        'cancelled' => $session->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Test'])->save(),
        'type' => $session->trainingType->update(['archived_at' => now()]),
        'room' => $session->room->update(['archived_at' => now()]),
        'instructor' => $session->instructor->update(['archived_at' => now()]),
        'inactive' => $session->instructor->user->forceFill(['is_active' => false])->save(),
        'role' => $session->instructor->user->forceFill(['role' => 'customer'])->save(),
    };
    expect(fn () => app(CreateBooking::class)->handle($customer, bookingRequest($session)))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 1);
})->with(['draft', 'cancelled', 'completed', 'type', 'room', 'instructor', 'inactive', 'role']);

test('booking window has inclusive notice and local calendar horizon boundaries', function (string $start, bool $allowed) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $session->forceFill(['starts_at' => $start, 'ends_at' => CarbonImmutable::parse($start, 'UTC')->addHour()])->save();
    $grant->forceFill(['expires_at' => '2027-01-01 00:00:00'])->save();
    if ($allowed) {
        app(CreateBooking::class)->handle($customer, bookingRequest($session));
        $this->assertDatabaseCount('bookings', 1);
    } else {
        expect(fn () => app(CreateBooking::class)->handle($customer, bookingRequest($session)))->toThrow(ValidationException::class);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('credit_entries', 1);
    }
})->with([
    ['2026-10-12 12:00:00', true], ['2026-10-12 11:59:59', false], ['2026-10-12 10:00:00', false],
    ['2026-12-07 11:00:00', true], ['2026-12-07 11:00:01', false],
]);

test('grant selection skips empty uncovered and invalid grants then uses earliest expiry and id', function () {
    [$tenant, $customer, $session, $empty] = bookingSetup();
    CreditEntry::factory()->for($empty, 'grant')->create(['kind' => 'adjustment', 'amount' => -1, 'reason' => 'Correction']);
    bookingGrant($customer, $session->training_type_id, overrides: ['expires_at' => $session->starts_at]);
    bookingGrant($customer, $session->training_type_id, overrides: ['valid_from' => $session->starts_at->addSecond()]);
    $uncovered = bookingGrant($customer, $session->training_type_id, overrides: ['expires_at' => '2026-10-20 10:00:00']);
    $uncovered->trainingTypes()->detach();
    $chosen = bookingGrant($customer, $session->training_type_id, overrides: ['valid_from' => $session->starts_at, 'expires_at' => '2026-10-21 10:00:00']);
    bookingGrant($customer, $session->training_type_id, overrides: ['expires_at' => '2026-10-21 10:00:00']);
    expect(app(CreateBooking::class)->handle($customer, bookingRequest($session))->credit_grant_id)->toBe($chosen->id);
});

test('full session and duplicate or overlapping customer reservations never spend another credit', function (string $case) {
    [$tenant, $customer, $session] = bookingSetup();
    $session->forceFill(['capacity' => 1])->save();
    app(CreateBooking::class)->handle($customer, bookingRequest($session));
    if ($case === 'full') {
        $customer = User::factory()->for($tenant)->create();
    }
    bookingGrant($customer, $session->training_type_id);
    $target = $case === 'overlap' ? ClassSession::factory()->for($session->trainingType)->create(['status' => 'published', 'starts_at' => '2026-10-19 10:30:00', 'ends_at' => '2026-10-19 11:30:00']) : $session;
    expect(fn () => app(CreateBooking::class)->handle($customer, bookingRequest($target)))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('credit_entries', 3);
    $this->assertDatabaseCount('outbox_events', 1);
})->with(['full', 'duplicate', 'overlap']);

test('no credit rolls back a newly materialized private session', function () {
    [$tenant, $customer] = bookingSetup();
    $rule = bookingPrivate($customer);
    DB::table('credit_grant_training_type')->where('tenant_id', $tenant->id)->where('training_type_id', $rule->training_type_id)->delete();
    expect(fn () => app(CreateBooking::class)->handle($customer, ['availability_rule_id' => $rule->id, 'starts_at' => '2026-10-19T10:00:00+00:00', 'operation_key' => (string) Str::uuid()]))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('class_sessions', 1);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseCount('outbox_events', 0);
});

test('outbox failure rolls back booking debit private session and audit', function () {
    [$tenant, $customer] = bookingSetup();
    $rule = bookingPrivate($customer);
    $events = OutboxEvent::getEventDispatcher();
    OutboxEvent::setEventDispatcher(clone $events);
    OutboxEvent::creating(function (): void {
        throw new RuntimeException('Outbox unavailable');
    });
    try {
        expect(fn () => app(CreateBooking::class)->handle($customer, ['availability_rule_id' => $rule->id, 'starts_at' => '2026-10-19T10:00:00+00:00', 'operation_key' => (string) Str::uuid()]))->toThrow(RuntimeException::class, 'Outbox unavailable');
    } finally {
        OutboxEvent::setEventDispatcher($events);
    }
    $this->assertDatabaseCount('class_sessions', 1);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
    $this->assertDatabaseCount('outbox_events', 0);
});

test('stale unavailable or off-grid private intent returns a useful conflict without a session or debit', function (string $case) {
    [$tenant, $customer, $group] = bookingSetup();
    $rule = bookingPrivate($customer);
    $start = '2026-10-19T10:00:00+00:00';
    if ($case === 'closed') {
        $rule->forceFill(['archived_at' => now()])->save();
    } elseif ($case === 'off-grid') {
        $start = '2026-10-19T10:01:00+00:00';
    } elseif ($case === 'outside-window') {
        $start = '2026-10-19T18:00:00+00:00';
    } else {
        $group->forceFill(['room_id' => $rule->room_id])->save();
    }
    $this->signInToStudio($customer);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings', [
        'availability_rule_id' => $rule->id, 'starts_at' => $start, 'operation_key' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonPath('errors.starts_at.0', 'Odabrani privatni termin više nije dostupan. Osvježite raspored.');
    $this->assertDatabaseCount('class_sessions', 1);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 2);
})->with(['closed', 'off-grid', 'outside-window', 'occupied']);

test('adjacent private protected intervals and adjacent customer bookings are allowed', function () {
    [$tenant, $customer] = bookingSetup();
    $rule = bookingPrivate($customer);
    bookingGrant($customer, $rule->training_type_id);
    $create = app(CreateBooking::class);
    $create->handle($customer, ['availability_rule_id' => $rule->id, 'starts_at' => '2026-10-19T10:00:00+00:00', 'operation_key' => (string) Str::uuid()]);
    $create->handle($customer, ['availability_rule_id' => $rule->id, 'starts_at' => '2026-10-19T11:30:00+00:00', 'operation_key' => (string) Str::uuid()]);
    $this->assertDatabaseCount('bookings', 2);
    $this->assertDatabaseCount('class_sessions', 3);
    $this->assertDatabaseCount('credit_entries', 5);
});

test('customer can book adjacent group times with no overlap', function () {
    [$tenant, $customer, $first] = bookingSetup();
    bookingGrant($customer, $first->training_type_id);
    $second = ClassSession::factory()->for($first->trainingType)->create(['status' => 'published', 'starts_at' => '2026-10-19 11:00:00', 'ends_at' => '2026-10-19 12:00:00']);
    app(CreateBooking::class)->handle($customer, bookingRequest($first));
    app(CreateBooking::class)->handle($customer, bookingRequest($second));
    $this->assertDatabaseCount('bookings', 2);
});

test('an invalid or empty sole grant cannot finance a booking', function (string $case) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    if ($case === 'future') {
        $grant->forceFill(['valid_from' => $session->starts_at->addSecond()])->save();
    } elseif ($case === 'expired') {
        $grant->forceFill(['expires_at' => $session->starts_at])->save();
    } elseif ($case === 'wrong-type') {
        $grant->trainingTypes()->detach();
    } else {
        CreditEntry::factory()->for($grant, 'grant')->create(['kind' => 'adjustment', 'amount' => -1, 'reason' => 'Correction']);
    }
    $this->signInToStudio($customer);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings', bookingRequest($session))
        ->assertUnprocessable()->assertJsonPath('errors.credit_grant_id.0', 'Nemate raspoloživ kredit koji vrijedi za ovaj termin. Kontaktirajte studio.');
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseMissing('credit_entries', ['kind' => 'debit']);
    $this->assertDatabaseCount('outbox_events', 0);
})->with(['future', 'expired', 'wrong-type', 'empty']);

test('missing tenant context fails closed and a foreign actor cannot use the service', function () {
    [$tenant, $customer, $session] = bookingSetup();
    app(TenantContext::class)->clear();
    expect(fn () => app(CreateBooking::class)->handle($customer, bookingRequest($session)))->toThrow(LogicException::class);
    app(TenantContext::class)->setTenant(Tenant::factory()->active()->create());
    expect(fn () => app(CreateBooking::class)->handle($customer, bookingRequest($session)))->toThrow(HttpException::class);
    $this->assertDatabaseCount('bookings', 0);
});

test('rule changes cannot rewrite snapshots or reactivate a cancelled booking on retry', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $data = bookingRequest($session);
    $booking = app(CreateBooking::class)->handle($customer, $data);
    $snapshot = $booking->rules_snapshot;
    $session->forceFill(['booking_rule_version_id' => BookingRuleVersion::factory()->create(['group_cancellation_minutes' => 100])->id])->save();
    $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Test'])->save();
    $retry = app(CreateBooking::class)->handle($customer, $data);
    expect($retry->status)->toBe('cancelled');
    expect($retry->rules_snapshot)->toEqual($snapshot);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseCount('outbox_events', 1);
});
