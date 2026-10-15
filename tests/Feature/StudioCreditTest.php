<?php

use App\Actions\CreditLedger;
use App\Actions\ManageStudioCredits;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditGrant;
use App\Models\PackageProduct;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

function creditSetup(string $role = 'owner'): array
{
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test', 'is_primary' => true]);
    TenantProfile::factory()->for($tenant)->create();
    $owner = User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
    $customer = User::factory()->for($tenant)->create(['role' => 'customer']);
    $type = TrainingType::factory()->create();
    $product = PackageProduct::factory()->create(['credits' => 5, 'validity_days' => 45]);
    $product->trainingTypes()->attach($type);

    return [$owner, $customer, $product, $type];
}

function creditData(User $customer, PackageProduct $product, array $overrides = []): array
{
    return [...['user_id' => $customer->id, 'package_product_id' => $product->id, 'source' => 'demo', 'operation_key' => (string) Str::uuid()], ...$overrides];
}

function creditBooking(CreditGrant $grant, TrainingType $type, string $start = '2026-10-20 10:00:00'): Booking
{
    $session = ClassSession::factory()->for($type)->create(['status' => 'published', 'starts_at' => $start, 'ends_at' => CarbonImmutable::parse($start)->addHour()]);

    return Booking::factory()->for($grant, 'grant')->for($session, 'session')->create();
}

test('only a ready owner has credit management permission', function (string $role, bool $allowed) {
    [$actor] = creditSetup($role);
    expect(Gate::forUser($actor)->allows('studio-credits'))->toBe($allowed);
    $actor->is_active = false;
    expect(Gate::forUser($actor)->allows('studio-credits'))->toBeFalse();
})->with([['owner', true], ['manager', false], ['instructor', false], ['customer', false]]);

test('foreign identities and platform admins cannot manage studio credits', function () {
    [$owner] = creditSetup();
    app(TenantContext::class)->setTenant(Tenant::factory()->active()->create());
    expect(Gate::forUser($owner)->allows('studio-credits'))->toBeFalse();
    expect(Gate::forUser(PlatformAdmin::factory()->create())->allows('studio-credits'))->toBeFalse();
});

test('credit endpoints require authentication and refuse manager mutations', function () {
    [$manager, $customer, $product] = creditSetup('manager');
    $this->postJson('http://lotus.yoga.test/studio/credits', creditData($customer, $product))->assertUnauthorized();
    $this->signInToStudio($manager);
    $this->get('http://lotus.yoga.test/studio/credits')->assertForbidden();
    $this->postJson('http://lotus.yoga.test/studio/credits', creditData($customer, $product))->assertForbidden();
    app(TenantContext::class)->setTenant($manager->tenant);
    $grant = CreditGrant::factory()->create();
    $this->postJson('http://lotus.yoga.test/studio/credits/'.$grant->id.'/adjust', ['amount' => 1, 'reason' => 'Test', 'operation_key' => (string) Str::uuid()])->assertForbidden();
    $this->assertDatabaseCount('credit_entries', 0);
});

test('owner without enrolled two factor cannot manage credits', function () {
    [$owner, $customer, $product] = creditSetup();
    $owner->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();
    expect(Gate::forUser($owner)->allows('studio-credits'))->toBeFalse();
    $this->signInToStudio($owner);
    $this->post('http://lotus.yoga.test/studio/credits', creditData($customer, $product))->assertRedirect('http://lotus.yoga.test/settings/two-factor');
    $this->assertDatabaseCount('credit_grants', 0);
});

test('owner grants documented sources once and renders the ledger without platform revenue', function (string $source, ?string $reference, ?string $reason) {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product] = creditSetup();
    $data = creditData($customer, $product, ['source' => $source, 'source_reference' => $reference, 'reason' => $reason]);
    $this->signInToStudio($owner);
    $this->post('http://lotus.yoga.test/studio/credits', $data)->assertRedirect();
    $this->post('http://lotus.yoga.test/studio/credits', $data)->assertRedirect();
    $this->assertDatabaseCount('credit_grants', 1);
    $this->assertDatabaseCount('credit_entries', 1);
    $this->assertDatabaseCount('tenant_audit_logs', 1);
    $this->assertDatabaseHas('credit_grants', ['source' => $source, 'source_reference' => $reference, 'reason' => $reason, 'created_by_user_id' => $owner->id, 'expires_at' => '2026-11-26 11:00:00']);
    $this->get('http://lotus.yoga.test/studio/credits')->assertOk()->assertInertia(fn (Assert $page) => $page->component('studio/Credits')->has('grants.data', 1)->where('grants.data.0.source', $source)->where('grants.data.0.totals.available', 5)->missing('revenue')->missing('grants.data.0.user.password'));
})->with([['demo', null, null], ['external_purchase', 'R-2026-42', null], ['complimentary', null, 'Poklon studija']]);

test('grant validation rejects undocumented or client controlled entitlement', function (array $overrides, string $field) {
    [$owner, $customer, $product] = creditSetup();
    $this->signInToStudio($owner);
    $this->postJson('http://lotus.yoga.test/studio/credits', creditData($customer, $product, $overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('credit_grants', 0);
    $this->assertDatabaseCount('credit_entries', 0);
})->with([
    [['source' => 'external_purchase'], 'source_reference'],
    [['source' => 'complimentary', 'reason' => '   '], 'reason'],
    [['source' => 'stripe'], 'source'], [['operation_key' => 'invalid'], 'operation_key'],
    [['credits' => 999], 'credits'], [['tenant_id' => 10], 'tenant_id'],
    [['expires_at' => '2099-01-01'], 'expires_at'], [['created_by_user_id' => 10], 'created_by_user_id'],
]);

test('snapshot and allowed types survive product edits and retries after archival', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $data = creditData($customer, $product);
    $manage = app(ManageStudioCredits::class);
    $grant = $manage->grant($owner, $data);
    $snapshot = $grant->product_snapshot;
    $product->update(['credits' => 20, 'validity_days' => 90, 'name' => 'New', 'archived_at' => now()]);
    $product->trainingTypes()->sync([TrainingType::factory()->create()->id]);
    $this->travel(1)->days();
    expect($manage->grant($owner, $data)->id)->toBe($grant->id);
    expect($grant->fresh()->product_snapshot)->toEqual($snapshot);
    expect($grant->trainingTypes->modelKeys())->toBe([$type->id]);
    expect(fn () => $manage->grant($owner, [...$data, 'source' => 'complimentary', 'reason' => 'Changed']))->toThrow(ValidationException::class);
    expect(fn () => $manage->grant($owner, creditData($customer, $product)))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('credit_entries', 1);
});

test('foreign customer product and grant identifiers are not accessible', function () {
    [$owner, $customer, $product] = creditSetup();
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    $foreignUser = User::factory()->for($other)->create(['email' => $customer->email]);
    $foreignProduct = PackageProduct::factory()->create();
    $foreignGrant = CreditGrant::factory()->create();
    $this->signInToStudio($owner);
    $this->postJson('http://lotus.yoga.test/studio/credits', creditData($foreignUser, $product))->assertNotFound();
    $this->postJson('http://lotus.yoga.test/studio/credits', creditData($customer, $foreignProduct))->assertNotFound();
    $this->postJson('http://lotus.yoga.test/studio/credits/'.$foreignGrant->id.'/adjust', ['amount' => 1, 'reason' => 'Test', 'operation_key' => (string) Str::uuid()])->assertNotFound();
    $this->get('http://lotus.yoga.test/studio/credits')->assertOk()->assertInertia(fn (Assert $page) => $page->has('grants.data', 0)->has('customers', 1)->where('customers.0.id', $customer->id));
    $this->assertDatabaseCount('credit_entries', 0);
});

test('inactive customers and staff cannot receive grants', function (string $role, bool $active) {
    [$owner, $customer, $product] = creditSetup();
    $customer->forceFill(['role' => $role, 'is_active' => $active])->save();
    expect(fn () => app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product)))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('credit_entries', 0);
})->with([['customer', false], ['manager', true], ['owner', true], ['instructor', true]]);

test('correction is append only idempotent audited and cannot make the balance negative', function () {
    [$owner, $customer, $product] = creditSetup();
    $grant = app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product));
    $this->signInToStudio($owner);
    $url = 'http://lotus.yoga.test/studio/credits/'.$grant->id.'/adjust';
    $data = ['amount' => -5, 'reason' => 'Ispravak pogrešne dodjele', 'operation_key' => (string) Str::uuid()];
    $this->post($url, $data)->assertRedirect();
    $this->post($url, $data)->assertRedirect();
    $this->postJson($url, [...$data, 'amount' => -1])->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $this->postJson($url, [...$data, 'amount' => -1, 'operation_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseCount('tenant_audit_logs', 2);
    app(TenantContext::class)->setTenant($owner->tenant);
    expect((int) $grant->entries()->sum('amount'))->toBe(0);
    $this->post($url, [...$data, 'amount' => 2, 'operation_key' => (string) Str::uuid()])->assertRedirect();
    $this->assertDatabaseHas('credit_entries', ['kind' => 'adjustment', 'amount' => 2, 'created_by_user_id' => $owner->id]);
});

test('corrections require nonzero bounded integers and a reason', function (array $overrides, string $field) {
    [$owner] = creditSetup();
    $grant = CreditGrant::factory()->create();
    $this->signInToStudio($owner);
    $this->postJson('http://lotus.yoga.test/studio/credits/'.$grant->id.'/adjust', [...['amount' => 1, 'reason' => 'Test', 'operation_key' => (string) Str::uuid()], ...$overrides])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('credit_entries', 0);
})->with([[['amount' => 0], 'amount'], [['amount' => 1.5], 'amount'], [['amount' => 1001], 'amount'], [['reason' => ' '], 'reason']]);

test('debit and reversal are idempotent and reserved credits survive correction', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $manage = app(ManageStudioCredits::class);
    $ledger = app(CreditLedger::class);
    $grant = $manage->grant($owner, creditData($customer, $product));
    $booking = creditBooking($grant, $type);
    $key = (string) Str::uuid();
    $debit = $ledger->debit($customer, $booking, $key);
    expect($ledger->debit($customer, $booking, $key)->id)->toBe($debit->id);
    expect($ledger->debit($customer, $booking, (string) Str::uuid())->id)->toBe($debit->id);
    $manage->adjust($owner, $grant, ['amount' => -4, 'reason' => 'Ispravak', 'operation_key' => (string) Str::uuid()]);
    expect($ledger->balance($grant))->toBe(['balance' => 0, 'available' => 0, 'reserved' => 1, 'expired' => 0, 'pending' => 0]);
    expect(fn () => $manage->adjust($owner, $grant, ['amount' => -1, 'reason' => 'Nedopušteno', 'operation_key' => (string) Str::uuid()]))->toThrow(ValidationException::class);
    $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'customer_timely'])->save();
    $reversal = $ledger->reverse($customer, $booking, $key);
    expect($ledger->reverse($customer, $booking, $key)->id)->toBe($reversal->id);
    expect($ledger->reverse($customer, $booking, (string) Str::uuid())->id)->toBe($reversal->id);
    expect($ledger->balance($grant)['available'])->toBe(1);
    $this->assertDatabaseCount('credit_entries', 4);
    $this->assertDatabaseHas('credit_entries', ['id' => $reversal->id, 'reverses_entry_id' => $debit->id, 'amount' => 1]);
});

test('debit validates the training start against the half open grant window', function (string $start, bool $valid) {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $grant = app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product));
    $booking = creditBooking($grant, $type, $start);
    if ($valid) {
        app(CreditLedger::class)->debit($customer, $booking, (string) Str::uuid());
        $this->assertDatabaseHas('credit_entries', ['booking_id' => $booking->id, 'kind' => 'debit', 'amount' => -1]);
    } else {
        expect(fn () => app(CreditLedger::class)->debit($customer, $booking, (string) Str::uuid()))->toThrow(ValidationException::class);
        $this->assertDatabaseCount('credit_entries', 1);
    }
})->with([['2026-10-12 09:59:59', false], ['2026-10-12 10:00:00', true], ['2026-11-26 10:59:59', true], ['2026-11-26 11:00:00', false]]);

test('expired reversal preserves original expiry and never revives credits', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $grant = app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product));
    $booking = creditBooking($grant, $type);
    $ledger = app(CreditLedger::class);
    $ledger->debit($customer, $booking, (string) Str::uuid());
    $this->travelTo(CarbonImmutable::parse('2026-11-26 11:00:00', 'UTC'));
    $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'owner_exception'])->save();
    $ledger->reverse($owner, $booking, (string) Str::uuid());
    expect($ledger->balance($grant))->toBe(['balance' => 5, 'available' => 0, 'reserved' => 0, 'expired' => 5, 'pending' => 0]);
    expect($grant->fresh()->expires_at->format('Y-m-d H:i:s'))->toBe('2026-11-26 11:00:00');
});

test('attendance never debits and rejected ledger movements leave credits intact', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $grant = app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product));
    $booking = creditBooking($grant, $type);
    $ledger = app(CreditLedger::class);
    expect(fn () => $ledger->reverse($customer, $booking, (string) Str::uuid()))->toThrow(ValidationException::class);
    $ledger->debit($customer, $booking, (string) Str::uuid());
    AttendanceRecord::factory()->for($booking)->create();
    expect($ledger->balance($grant)['balance'])->toBe(4);
    $this->assertDatabaseCount('credit_entries', 2);
    $wrongTypeBooking = creditBooking($grant, TrainingType::factory()->create());
    expect(fn () => $ledger->debit($customer, $wrongTypeBooking, (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('credit_entries', 2);
});

test('insufficient credit rolls back the enclosing booking transaction', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $grant = app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product));
    app(ManageStudioCredits::class)->adjust($owner, $grant, ['amount' => -5, 'reason' => 'Ispravak', 'operation_key' => (string) Str::uuid()]);
    expect(fn () => DB::transaction(function () use ($grant, $type, $customer): void {
        Tenant::query()->lockForUpdate()->findOrFail($grant->tenant_id);
        $booking = creditBooking($grant, $type);
        app(CreditLedger::class)->debit($customer, $booking, (string) Str::uuid());
    }))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('class_sessions', 0);
    expect(app(CreditLedger::class)->balance($grant)['balance'])->toBe(0);
});

test('grant validity uses local calendar days across both DST transitions', function (string $now, string $expires) {
    $this->travelTo(CarbonImmutable::parse($now, 'UTC'));
    [$owner, $customer, $product] = creditSetup();
    $product->update(['validity_days' => 1]);
    $grant = app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product));
    expect($grant->expires_at->format('Y-m-d H:i:s'))->toBe($expires);
})->with([['2026-03-28 11:00:00', '2026-03-29 10:00:00'], ['2026-10-24 10:00:00', '2026-10-25 11:00:00']]);

test('a revoked owner cannot use an already loaded identity to grant credits', function () {
    [$owner, $customer, $product] = creditSetup();
    User::query()->whereKey($owner->id)->update(['role' => 'manager']);
    expect(fn () => app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product)))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('credit_grants', 0);
});

test('inactive training types and empty packages cannot create new rights', function (bool $empty) {
    [$owner, $customer, $product, $type] = creditSetup();
    if ($empty) {
        $product->trainingTypes()->detach();
    } else {
        $type->update(['archived_at' => now()]);
    }
    expect(fn () => app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product)))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('credit_entries', 0);
})->with([true, false]);

test('operation keys cannot be reused for another grant correction or booking', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $manage = app(ManageStudioCredits::class);
    $grant = $manage->grant($owner, creditData($customer, $product));
    $anotherGrant = $manage->grant($owner, creditData($customer, $product));
    $data = ['amount' => 1, 'reason' => 'Ispravak', 'operation_key' => (string) Str::uuid()];
    $manage->adjust($owner, $grant, $data);
    expect(fn () => $manage->adjust($owner, $anotherGrant, $data))->toThrow(ValidationException::class);
    $booking = creditBooking($grant, $type);
    $otherBooking = creditBooking($anotherGrant, $type);
    $ledger = app(CreditLedger::class);
    $key = (string) Str::uuid();
    $ledger->debit($customer, $booking, $key);
    expect(fn () => $ledger->debit($customer, $otherBooking, $key))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('credit_entries', 4);
});

test('internal ledger rejects another customer and foreign tenant records', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $grant = app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product));
    $booking = creditBooking($grant, $type);
    $otherCustomer = User::factory()->for($owner->tenant)->create();
    $ledger = app(CreditLedger::class);
    expect(fn () => $ledger->debit($otherCustomer, $booking, (string) Str::uuid()))->toThrow(HttpException::class);
    app(TenantContext::class)->setTenant(Tenant::factory()->active()->create());
    expect(fn () => $ledger->balance($grant))->toThrow(HttpException::class);
    expect(fn () => $ledger->debit($customer, $booking, (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
    $this->assertDatabaseCount('credit_entries', 1);
});

test('ledger cannot be rewritten or deleted through model operations', function () {
    [$owner, $customer, $product] = creditSetup();
    $grant = app(ManageStudioCredits::class)->grant($owner, creditData($customer, $product));
    $entry = $grant->entries()->sole();
    expect(fn () => $entry->forceFill(['amount' => 999])->save())->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
    expect(fn () => $grant->entries()->update(['amount' => 999]))->toThrow(LogicException::class);
    expect(fn () => $grant->entries()->delete())->toThrow(LogicException::class);
    $this->assertDatabaseHas('credit_entries', ['id' => $entry->id, 'amount' => 5]);
});

test('correction protects a confirmed booking that has not yet been debited', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
    [$owner, $customer, $product, $type] = creditSetup();
    $manage = app(ManageStudioCredits::class);
    $grant = $manage->grant($owner, creditData($customer, $product));
    creditBooking($grant, $type);
    expect(fn () => $manage->adjust($owner, $grant, ['amount' => -5, 'reason' => 'Ispravak', 'operation_key' => (string) Str::uuid()]))->toThrow(ValidationException::class);
    expect(app(CreditLedger::class)->balance($grant)['balance'])->toBe(5);
});
