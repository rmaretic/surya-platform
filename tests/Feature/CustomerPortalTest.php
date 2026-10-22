<?php

use App\Actions\CreateBooking;
use App\Models\ClassSession;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../BookingFixtures.php';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
});

test('public schedule exposes only published group availability and safe fields on both designs', function (string $design) {
    [$tenant, $customer, $session] = bookingSetup();
    $tenant->update(['design_key' => $design]);
    app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    ClassSession::factory()->create(['status' => 'draft', 'starts_at' => $session->starts_at, 'ends_at' => $session->ends_at]);
    $this->signInToStudio($customer);
    $this->get('http://booking-'.$tenant->id.'.yoga.test/schedule?date=2026-10-19')->assertInertia(fn (Assert $page) => $page
        ->component('sites/'.$design.'/Schedule')->has('items', 1)->where('items.0.target.class_session_id', $session->id)
        ->where('items.0.remaining', $session->capacity - 1)->where('timezone', 'Europe/Zagreb')
        ->where('auth.user', null)->where('authenticated', false)->missing('items.0.bookings')->missing('items.0.user_id')->missing('studioAccess'));
})->with(['lotus', 'balance']);

test('date type and instructor filters apply to groups and private slots without tenant leakage', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $private = bookingPrivate($customer);
    [$other, , $foreign] = bookingSetup();
    $url = 'http://booking-'.$tenant->id.'.yoga.test/schedule?date=2026-10-19';
    $this->get($url.'&type='.$private->training_type_id.'&instructor='.$private->instructor_profile_id)->assertInertia(fn (Assert $page) => $page
        ->where('items', fn ($items) => count($items) > 0 && collect($items)->every(fn ($item) => $item['mode'] === 'private' && $item['target']['availability_rule_id'] === $private->id)));
    $this->get($url.'&type='.$foreign->training_type_id)->assertInertia(fn (Assert $page) => $page->has('items', 0));
    $this->get($url.'&type='.$session->training_type_id)->assertInertia(fn (Assert $page) => $page->has('items', 1));
    $this->get('http://booking-'.$other->id.'.yoga.test/schedule?date=2026-10-19')->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.target.class_session_id', $foreign->id));
    $this->get('http://booking-'.$tenant->id.'.yoga.test/schedule?date=2026-10-20')->assertInertia(fn (Assert $page) => $page->has('items', 0));
});

test('portal lists only own bookings grants and safe credit history and protects detail', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $booking = app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $other = User::factory()->for($tenant)->create(['role' => 'customer']);
    bookingGrant($other, $session->training_type_id);
    $foreignBooking = app(CreateBooking::class)->handle($other, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $this->signInToStudio($customer);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/portal';
    $this->get($url.'?booking='.$booking->id)->assertInertia(fn (Assert $page) => $page
        ->has('bookings.data', 1)->where('bookings.data.0.id', $booking->id)->has('grants.data', 1)
        ->where('grants.data.0.id', $grant->id)->where('grants.data.0.balance.available', 0)->where('grants.data.0.balance.reserved', 1)
        ->missing('grants.data.0.reason')->missing('grants.data.0.source_reference')->missing('grants.data.0.created_by_user_id')
        ->has('entries.data', 2)->missing('entries.data.0.reason')->missing('entries.data.0.operation_key')
        ->where('selected.preview.can_cancel', true)->where('selected.preview.can_move', true));
    $this->get($url.'?booking='.$foreignBooking->id)->assertNotFound();
    $this->get('http://booking-'.$tenant->id.'.yoga.test/reserve?move='.$foreignBooking->id)->assertNotFound();
    $this->get($url.'?period=history')->assertInertia(fn (Assert $page) => $page->has('bookings.data', 0));
});

test('both designs use confirmed server booking move and cancellation with stable retry', function (string $design) {
    [$tenant, $customer, $session] = bookingSetup();
    $tenant->update(['design_key' => $design]);
    $target = ClassSession::factory()->create(['training_type_id' => $session->training_type_id, 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00', 'status' => 'published']);
    $this->signInToStudio($customer);
    $base = 'http://booking-'.$tenant->id.'.yoga.test';
    $this->get($base.'/reserve?date=2026-10-19&intent=session-'.$session->id)->assertInertia(fn (Assert $page) => $page->component('sites/'.$design.'/Schedule')->where('intent', 'session-'.$session->id)->where('authenticated', true));
    $data = ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()];
    $id = $this->postJson($base.'/bookings', $data)->assertOk()->json('booking.id');
    $this->postJson($base.'/bookings', $data)->assertJsonPath('booking.id', $id);
    $this->get($base.'/portal?booking='.$id)->assertInertia(fn (Assert $page) => $page->component('sites/'.$design.'/Portal')->where('selected.status', 'confirmed'));
    $moved = $this->postJson($base.'/bookings/'.$id.'/move', ['class_session_id' => $target->id, 'operation_key' => (string) Str::uuid()])->assertOk()->json('booking.id');
    $this->postJson($base.'/bookings/'.$moved.'/cancel', ['operation_key' => (string) Str::uuid()])->assertJsonPath('booking.status', 'cancelled');
    $this->assertDatabaseHas('bookings', ['id' => $id, 'status' => 'cancelled', 'cancellation_reason' => 'moved']);
    $this->assertDatabaseHas('bookings', ['id' => $moved, 'status' => 'cancelled']);
    $this->assertDatabaseCount('bookings', 2);
    $this->assertDatabaseCount('credit_entries', 5);
})->with(['lotus', 'balance']);

test('guest selection is stored only as intended navigation without creating a booking', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $url = 'http://booking-'.$tenant->id.'.yoga.test/reserve?date=2026-10-19&intent=session-'.$session->id;
    $response = $this->get($url)->assertRedirect();
    $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
    $this->post('http://booking-'.$tenant->id.'.yoga.test/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect($url);
    $this->assertDatabaseCount('bookings', 0);
});

test('portal rejects staff and cross tenant customer sessions', function () {
    [$tenant, $customer] = bookingSetup();
    [$other] = bookingSetup();
    $this->signInToStudio($customer);
    $this->get('http://booking-'.$other->id.'.yoga.test/portal')->assertRedirect();
    app(TenantContext::class)->setTenant($tenant);
    $staff = User::factory()->for($tenant)->withTwoFactor()->create(['role' => 'manager']);
    $this->post('http://booking-'.$tenant->id.'.yoga.test/logout');
    $this->signInToStudio($staff);
    $this->get('http://booking-'.$tenant->id.'.yoga.test/portal')->assertForbidden();
});

test('invalid schedule filters and unknown hosts fail safely', function () {
    [$tenant] = bookingSetup();
    $this->getJson('http://booking-'.$tenant->id.'.yoga.test/schedule?date=invalid')->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->get('http://unknown.yoga.test/schedule')->assertNotFound();
});

test('credit eligibility reflects the training date service and remaining ledger balance', function () {
    [$tenant, $customer, $session, $grant] = bookingSetup();
    $this->signInToStudio($customer);
    $url = 'http://booking-'.$tenant->id.'.yoga.test/reserve?date=2026-10-19';
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('items.0.has_credit', true));
    app(TenantContext::class)->setTenant($tenant);
    $grant->forceFill(['expires_at' => $session->starts_at])->save();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('items.0.has_credit', false));
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings', ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()])
        ->assertUnprocessable()->assertJsonValidationErrors('credit_grant_id');
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 1);
});

test('full session is visible but unavailable and stale private selection returns a useful conflict', function () {
    [$tenant, $customer, $session] = bookingSetup();
    $session->forceFill(['capacity' => 1])->save();
    app(CreateBooking::class)->handle($customer, ['class_session_id' => $session->id, 'operation_key' => (string) Str::uuid()]);
    $private = bookingPrivate($customer);
    $this->get('http://booking-'.$tenant->id.'.yoga.test/schedule?date=2026-10-19&type='.$session->training_type_id)->assertInertia(fn (Assert $page) => $page
        ->where('items.0.remaining', 0)->where('items.0.available', false)->where('items.0.unavailable_reason', 'Termin je popunjen.'));
    app(TenantContext::class)->setTenant($tenant);
    $private->forceFill(['archived_at' => now()])->save();
    $this->signInToStudio($customer);
    $this->postJson('http://booking-'.$tenant->id.'.yoga.test/bookings', ['availability_rule_id' => $private->id, 'starts_at' => '2026-10-19T14:00:00+00:00', 'operation_key' => (string) Str::uuid()])
        ->assertUnprocessable()->assertJsonPath('errors.starts_at.0', 'Odabrani privatni termin više nije dostupan. Osvježite raspored.');
    $this->assertDatabaseCount('bookings', 1);
});

test('private schedule slot confirms through the same customer endpoint on each design', function (string $design) {
    [$tenant, $customer] = bookingSetup();
    $tenant->update(['design_key' => $design]);
    $private = bookingPrivate($customer);
    $this->signInToStudio($customer);
    $base = 'http://booking-'.$tenant->id.'.yoga.test';
    $response = $this->get($base.'/reserve?date=2026-10-19&type='.$private->training_type_id)
        ->assertInertia(fn (Assert $page) => $page->component('sites/'.$design.'/Schedule')->where('items.0.has_credit', true));
    $target = $response->viewData('page')['props']['items'][0]['target'];
    $this->postJson($base.'/bookings', [...$target, 'operation_key' => (string) Str::uuid()])->assertJsonPath('booking.status', 'confirmed');
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseHas('credit_entries', ['kind' => 'debit', 'amount' => -1]);
})->with(['lotus', 'balance']);
