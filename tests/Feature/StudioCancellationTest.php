<?php

use App\Actions\CreateBooking;
use App\Actions\CreditLedger;
use App\Actions\ManageBooking;
use App\Actions\ManageStudioCancellation;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditGrant;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../BookingFixtures.php';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
});

function studioCancellationData(User $actor, ClassSession $session): array
{
    return ['operation_key' => (string) Str::uuid(), 'reason' => 'Bolest instruktora',
        'preview_token' => app(ManageStudioCancellation::class)->preview($actor, $session)['preview_token']];
}

function studioCancellationBooking(User $customer, ClassSession $session): Booking
{
    return app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
}

test('studio cancellation previews affected customers and returns every credit once', function (string $role) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = studioCancellationBooking($customer, $session);
    $second = User::factory()->for($tenant)->create();
    $secondGrant = bookingGrant($second, $session->training_type_id);
    studioCancellationBooking($second, $session);
    $actor = User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
    $data = studioCancellationData($actor, $session);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/studio/cancellations';
    $this->signInToStudio($actor);
    $this->get($url.'?date=2026-10-19&session='.$session->id)->assertInertia(fn (Assert $page) => $page
        ->component('studio/Cancellations')->has('sessions', 1)->has('selected.bookings', 2)
        ->where('selected.bookings.0.name', $customer->name)->where('selected.bookings.0.can_refund', $role === 'owner')
        ->missing('selected.bookings.0.email')->missing('selected.bookings.0.grant'));
    $this->post($url.'/sessions/'.$session->id, $data)->assertRedirect();
    $this->post($url.'/sessions/'.$session->id, $data)->assertRedirect();
    $this->postJson($url.'/sessions/'.$session->id, [...$data, 'reason' => 'Drugi razlog'])->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'cancelled', 'cancellation_reason' => $data['reason']]);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled', 'cancellation_reason' => 'studio_cancelled']);
    $this->assertDatabaseCount('credit_entries', 6);
    $this->assertDatabaseCount('credit_grants', 2);
    $this->assertDatabaseCount('outbox_events', 4);
    $this->assertDatabaseHas('tenant_audit_logs', ['action' => 'studio.session_cancelled', 'actor_user_id' => $actor->id]);
    app(TenantContext::class)->setTenant($tenant);
    expect((int) $grant->entries()->sum('amount'))->toBe(1);
    expect((int) $secondGrant->entries()->sum('amount'))->toBe(1);
})->with(['owner', 'manager']);

test('compensation extends only the returned unit using the booking snapshot across DST', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $session->forceFill(['starts_at' => '2026-10-24 10:00:00', 'ends_at' => '2026-10-24 11:00:00'])->save();
    $grant->forceFill(['expires_at' => '2026-10-24 12:00:00'])->save();
    $booking = studioCancellationBooking($customer, $session);
    $unrelated = bookingGrant($customer, $session->training_type_id, 4, ['expires_at' => '2026-10-20 10:00:00']);
    $session->ruleVersion->forceFill(['studio_refund_validity_days' => 30])->save();
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00 UTC'));
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $data = studioCancellationData($owner, $session);
    app(ManageStudioCancellation::class)->cancel($owner, $session, $data);
    app(ManageStudioCancellation::class)->cancel($owner, $session, $data);
    $compensation = CreditGrant::whereNotNull('compensates_credit_entry_id')->sole();
    expect($compensation->expires_at->format('Y-m-d H:i:s'))->toBe('2026-10-30 11:00:00');
    expect($compensation->compensates_credit_entry_id)->toBe($booking->entries()->where('kind', 'debit')->sole()->id);
    expect($compensation->trainingTypes()->pluck('training_types.id')->all())->toBe([$session->training_type_id]);
    expect(app(CreditLedger::class)->balance($compensation)['available'])->toBe(1);
    expect(app(CreditLedger::class)->balance($unrelated)['expired'])->toBe(4);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
    expect($grant->fresh()->expires_at->format('Y-m-d H:i:s'))->toBe('2026-10-24 12:00:00');
    $this->assertDatabaseCount('credit_entries', 6);
});

test('owner exception refunds a late cancellation or a past confirmed booking once', function (bool $lateCancellation) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = studioCancellationBooking($customer, $session);
    if ($lateCancellation) {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00:00 UTC'));
        app(ManageBooking::class)->handle($customer, $booking, ['operation_key' => (string) Str::uuid()]);
    }
    $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00:00 UTC'));
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $data = ['operation_key' => (string) Str::uuid(), 'reason' => 'Odobrena iznimka'];
    $this->signInToStudio($owner);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/studio/cancellations/bookings/'.$booking->id.'/refund';
    $this->post($url, $data)->assertRedirect();
    $this->post($url, $data)->assertRedirect();
    $this->postJson($url, [...$data, 'operation_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('booking');
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled', 'cancellation_reason' => $lateCancellation ? 'customer_late' : 'owner_exception']);
    $this->assertDatabaseHas('credit_grants', ['user_id' => $customer->id, 'credits' => 1, 'expires_at' => '2026-12-01 10:00:00']);
    $this->assertDatabaseCount('credit_entries', 5);
    $this->assertDatabaseCount('outbox_events', $lateCancellation ? 3 : 2);
    app(TenantContext::class)->setTenant($tenant);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
})->with([true, false]);

test('stale preview rejects without affecting a new booking', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $data = studioCancellationData($owner, $session);
    $booking = studioCancellationBooking($customer, $session);
    $this->signInToStudio($owner);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/studio/cancellations/sessions/'.$session->id, $data)
        ->assertUnprocessable()->assertJsonValidationErrors('preview_token');
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'published']);
    $this->assertDatabaseCount('credit_entries', 2);
});

test('batch failure rolls back earlier refunds session audit and outbox', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = studioCancellationBooking($customer, $session);
    $other = User::factory()->for($tenant)->create();
    $grant = bookingGrant($other, $session->training_type_id);
    Booking::factory()->for($other)->for($session, 'session')->for($grant, 'grant')->create();
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $data = studioCancellationData($owner, $session);
    $this->signInToStudio($owner);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/studio/cancellations/sessions/'.$session->id, $data)
        ->assertUnprocessable()->assertJsonValidationErrors('booking');
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'published']);
    $this->assertDatabaseCount('credit_entries', 3);
    $this->assertDatabaseCount('outbox_events', 1);
    $this->assertDatabaseMissing('tenant_audit_logs', ['action' => 'studio.session_cancelled']);
});

test('unprivileged roles cannot preview or cancel sessions and only owner can refund', function (string $role) {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = studioCancellationBooking($customer, $session);
    $actor = User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
    $this->signInToStudio($actor);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/studio/cancellations';
    if ($role !== 'manager') {
        $this->getJson($url.'?session='.$session->id)->assertForbidden();
        $this->postJson($url.'/sessions/'.$session->id, ['operation_key' => (string) Str::uuid(), 'reason' => 'Razlog', 'preview_token' => str_repeat('a', 64)])->assertForbidden();
    }
    $this->postJson($url.'/bookings/'.$booking->id.'/refund', ['operation_key' => (string) Str::uuid(), 'reason' => 'Razlog'])->assertForbidden();
    $this->assertDatabaseCount('credit_entries', 2);
})->with(['manager', 'instructor', 'customer']);

test('foreign session and booking identifiers are hidden on every cancellation endpoint', function () {
    [$foreign, $customer, $session] = bookingSetup();
    $booking = studioCancellationBooking($customer, $session);
    [$tenant] = bookingSetup();
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $this->signInToStudio($owner);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/studio/cancellations';
    $this->getJson($url.'?session='.$session->id)->assertNotFound();
    $this->postJson($url.'/sessions/'.$session->id, [])->assertNotFound();
    $this->postJson($url.'/bookings/'.$booking->id.'/refund', [])->assertNotFound();
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    $this->assertDatabaseCount('credit_entries', 3);
});

test('session cancellation validates required input and rejects client entitlement overrides', function (array $override, string $field) {
    [$tenant, $customer, $session] = bookingSetup();
    studioCancellationBooking($customer, $session);
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $data = studioCancellationData($owner, $session);
    $this->signInToStudio($owner);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/studio/cancellations/sessions/'.$session->id, [...$data, ...$override])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'published']);
})->with([
    [['reason' => '  '], 'reason'], [['operation_key' => 'invalid'], 'operation_key'],
    [['preview_token' => null], 'preview_token'], [['tenant_id' => 123], 'tenant_id'],
    [['credits' => 100], 'credits'], [['expires_at' => '2030-01-01'], 'expires_at'],
]);

test('started and completed sessions cannot be cancelled as future studio sessions', function (string $state) {
    [$tenant, $customer, $session] = bookingSetup();
    studioCancellationBooking($customer, $session);
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    if ($state === 'completed') {
        $session->forceFill(['status' => 'completed'])->save();
    } else {
        $this->travelTo($session->starts_at);
    }
    $data = studioCancellationData($owner, $session);
    $this->signInToStudio($owner);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/studio/cancellations/sessions/'.$session->id, $data)
        ->assertUnprocessable()->assertJsonValidationErrors('session');
    $this->assertDatabaseCount('credit_entries', 2);
})->with(['started', 'completed']);

test('already returned credit cannot be refunded through owner exception', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = studioCancellationBooking($customer, $session);
    app(ManageBooking::class)->handle($customer, $booking, ['operation_key' => (string) Str::uuid()]);
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    $this->signInToStudio($owner);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/studio/cancellations/bookings/'.$booking->id.'/refund', ['operation_key' => (string) Str::uuid(), 'reason' => 'Pokušaj duplikata'])
        ->assertUnprocessable()->assertJsonValidationErrors('booking');
    $this->assertDatabaseCount('credit_entries', 3);
});

test('empty draft cancellation records an audit without credits or recipient events', function () {
    [$tenant, , $session] = bookingSetup();
    $session->forceFill(['status' => 'draft'])->save();
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    app(ManageStudioCancellation::class)->cancel($owner, $session, studioCancellationData($owner, $session));
    expect($session->fresh()->status)->toBe('cancelled');
    $this->assertDatabaseCount('credit_entries', 1);
    $this->assertDatabaseCount('outbox_events', 0);
    $this->assertDatabaseHas('tenant_audit_logs', ['action' => 'studio.session_cancelled']);
});

test('private session cancellation and owner exception release the concrete interval', function (bool $exception) {
    [$tenant, $customer] = bookingSetup();
    $rule = bookingPrivate($customer);
    $booking = app(CreateBooking::class)->handle($customer, ['availability_rule_id' => $rule->id,
        'starts_at' => '2026-10-19T08:00:00+00:00', 'operation_key' => (string) Str::uuid()]);
    $session = $booking->session()->firstOrFail();
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    if ($exception) {
        app(ManageStudioCancellation::class)->refundException($owner, $booking, ['operation_key' => (string) Str::uuid(), 'reason' => 'Iznimka']);
    } else {
        app(ManageStudioCancellation::class)->cancel($owner, $session, studioCancellationData($owner, $session));
    }
    expect($session->fresh()->status)->toBe('cancelled');
    expect($booking->fresh()->status)->toBe('cancelled');
    expect($booking->entries()->where('kind', 'reversal')->count())->toBe(1);
    $replacement = app(CreateBooking::class)->handle($customer, ['availability_rule_id' => $rule->id,
        'starts_at' => '2026-10-19T08:00:00+00:00', 'operation_key' => (string) Str::uuid()]);
    expect($replacement->class_session_id)->not->toBe($session->id);
})->with([true, false]);

test('refund expiry boundary preserves the rest of the original grant', function (string $expiry, bool $compensated) {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = studioCancellationBooking($customer, $session);
    $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00:00 UTC'));
    $grant->forceFill(['expires_at' => $expiry])->save();
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'owner']);
    app(ManageStudioCancellation::class)->refundException($owner, $booking, ['operation_key' => (string) Str::uuid(), 'reason' => 'Iznimka']);
    expect(CreditGrant::whereNotNull('compensates_credit_entry_id')->count())->toBe($compensated ? 1 : 0);
    expect((int) $grant->entries()->sum('amount'))->toBe($compensated ? 0 : 1);
    expect($grant->fresh()->expires_at->format('Y-m-d H:i:s'))->toBe($expiry);
})->with([['2026-10-26 09:59:59', true], ['2026-10-26 10:00:00', false], ['2026-10-26 10:00:01', false]]);

test('guest cannot access cancellation data or mutations', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $booking = studioCancellationBooking($customer, $session);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/studio/cancellations';
    $this->getJson($url)->assertUnauthorized();
    $this->postJson($url.'/sessions/'.$session->id, [])->assertUnauthorized();
    $this->postJson($url.'/bookings/'.$booking->id.'/refund', [])->assertUnauthorized();
    $this->assertDatabaseCount('credit_entries', 2);
});
