<?php

use App\Models\AttendanceRecord;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\CreditGrant;
use App\Models\InstructorProfile;
use App\Models\OutboxEvent;
use App\Models\PackageProduct;
use App\Models\Room;
use App\Models\ScheduleSeries;
use App\Models\Tenant;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Database\Seeders\TestingBookingFoundationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('new business records require context and cannot be read or saved in another studio', function (string $model) {
    $this->prepareStudio();
    $record = $model::factory()->create();
    $otherStudio = Tenant::factory()->active()->create();

    app(TenantContext::class)->setTenant($otherStudio);

    expect($model::query()->find($record->id))->toBeNull();
    expect($record->resolveRouteBinding($record->id))->toBeNull();
    expect(fn () => $record->save())->toThrow(AuthorizationException::class);

    app(TenantContext::class)->clear();
    expect(fn () => $model::query()->count())->toThrow(LogicException::class, 'A tenant context is required.');
})->with([
    TrainingType::class, InstructorProfile::class, Room::class, BookingRuleVersion::class,
    ScheduleSeries::class, ClassSession::class, AvailabilityRule::class, AvailabilityException::class,
    PackageProduct::class, CreditGrant::class, CreditEntry::class, Booking::class,
    AttendanceRecord::class, OutboxEvent::class,
]);

test('mysql rejects a related record from another studio even through a raw update', function (string $model, string $column, string $related, array $changes = []) {
    $this->prepareStudio();
    $record = $model::factory()->create();
    $otherStudio = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($otherStudio);
    $foreign = $related === User::class
        ? User::factory()->for($otherStudio)->create()
        : $related::factory()->create();

    expect(fn () => DB::table($record->getTable())->where('id', $record->id)->update([
        ...$changes, $column => $foreign->id,
    ]))->toThrow(QueryException::class, 'foreign key constraint fails');
})->with([
    'instructor user' => [InstructorProfile::class, 'user_id', User::class],
    'rule author' => [BookingRuleVersion::class, 'created_by_user_id', User::class],
    'series type' => [ScheduleSeries::class, 'training_type_id', TrainingType::class],
    'series instructor' => [ScheduleSeries::class, 'instructor_profile_id', InstructorProfile::class],
    'series room' => [ScheduleSeries::class, 'room_id', Room::class],
    'series rules' => [ScheduleSeries::class, 'booking_rule_version_id', BookingRuleVersion::class],
    'session type' => [ClassSession::class, 'training_type_id', TrainingType::class],
    'session instructor' => [ClassSession::class, 'instructor_profile_id', InstructorProfile::class],
    'session room' => [ClassSession::class, 'room_id', Room::class],
    'session rules' => [ClassSession::class, 'booking_rule_version_id', BookingRuleVersion::class],
    'session series' => [ClassSession::class, 'schedule_series_id', ScheduleSeries::class, ['occurrence_key' => '2026-10-12T18:00']],
    'availability type' => [AvailabilityRule::class, 'training_type_id', TrainingType::class],
    'availability instructor' => [AvailabilityRule::class, 'instructor_profile_id', InstructorProfile::class],
    'availability room' => [AvailabilityRule::class, 'room_id', Room::class],
    'availability rules' => [AvailabilityRule::class, 'booking_rule_version_id', BookingRuleVersion::class],
    'availability exception' => [AvailabilityException::class, 'availability_rule_id', AvailabilityRule::class],
    'grant user' => [CreditGrant::class, 'user_id', User::class],
    'grant author' => [CreditGrant::class, 'created_by_user_id', User::class],
    'grant product' => [CreditGrant::class, 'package_product_id', PackageProduct::class],
    'compensation source' => [CreditGrant::class, 'compensates_credit_entry_id', CreditEntry::class],
    'booking user' => [Booking::class, 'user_id', User::class],
    'booking author' => [Booking::class, 'created_by_user_id', User::class],
    'booking canceller' => [Booking::class, 'cancelled_by_user_id', User::class, ['status' => 'cancelled', 'cancelled_at' => '2026-10-10 10:00:00', 'cancellation_reason' => 'studio']],
    'booking session' => [Booking::class, 'class_session_id', ClassSession::class],
    'booking grant' => [Booking::class, 'credit_grant_id', CreditGrant::class],
    'booking rules' => [Booking::class, 'booking_rule_version_id', BookingRuleVersion::class],
    'attendance booking' => [AttendanceRecord::class, 'booking_id', Booking::class],
    'attendance author' => [AttendanceRecord::class, 'recorded_by_user_id', User::class, ['status' => 'present', 'recorded_at' => '2026-10-12 16:00:00']],
    'outbox booking' => [OutboxEvent::class, 'booking_id', Booking::class],
    'outbox session' => [OutboxEvent::class, 'class_session_id', ClassSession::class],
    'outbox recipient' => [OutboxEvent::class, 'recipient_user_id', User::class],
]);

test('mysql rejects a booking funded by another customer in the same studio', function () {
    $this->prepareStudio();
    $booking = Booking::factory()->create();
    $anotherGrant = CreditGrant::factory()->create();

    expect(fn () => DB::table('bookings')->where('id', $booking->id)->update([
        'credit_grant_id' => $anotherGrant->id,
    ]))->toThrow(QueryException::class, 'foreign key constraint fails');
});

test('only one confirmed booking exists while cancellations and rebooking retain history', function () {
    $this->prepareStudio();
    $booking = Booking::factory()->create();

    expect(fn () => Booking::factory()->create([
        'user_id' => $booking->user_id, 'class_session_id' => $booking->class_session_id,
        'credit_grant_id' => $booking->credit_grant_id,
    ]))->toThrow(QueryException::class, 'bookings_active_unique');

    $booking->forceFill([
        'status' => 'cancelled', 'cancellation_reason' => 'customer_timely',
        'cancelled_at' => '2026-10-10 10:00:00',
    ])->save();
    $cancelled = Booking::factory()->cancelled()->create([
        'user_id' => $booking->user_id, 'class_session_id' => $booking->class_session_id,
        'credit_grant_id' => $booking->credit_grant_id,
    ]);
    $replacement = Booking::factory()->create([
        'user_id' => $booking->user_id, 'class_session_id' => $booking->class_session_id,
        'credit_grant_id' => $booking->credit_grant_id,
    ]);

    expect(Booking::query()->where('status', 'confirmed')->count())->toBe(1);
    expect(Booking::query()->where('status', 'cancelled')->count())->toBe(2);
    expect($replacement->id)->not->toBe($booking->id);
    expect($cancelled->fresh()->status)->toBe('cancelled');
    expect(fn () => DB::table('bookings')->where('id', $booking->id)->update([
        'status' => 'confirmed', 'cancelled_at' => null, 'cancellation_reason' => null,
    ]))->toThrow(QueryException::class, 'bookings_active_unique');
});

test('an owner may teach without changing role but an inactive user cannot get an active profile', function () {
    $this->prepareStudio();
    $owner = User::factory()->for($this->studio)->create(['role' => 'owner']);
    $profile = InstructorProfile::factory()->for($owner)->create();

    expect($profile->user->is($owner))->toBeTrue();
    expect($owner->fresh()->role)->toBe('owner');

    $inactive = User::factory()->for($this->studio)->create(['is_active' => false]);
    expect(fn () => InstructorProfile::factory()->for($inactive)->create())
        ->toThrow(InvalidArgumentException::class, 'active user');
});

test('one user has only one instructor profile', function () {
    $this->prepareStudio();
    $profile = InstructorProfile::factory()->create();

    expect(fn () => InstructorProfile::factory()->create(['user_id' => $profile->user_id]))
        ->toThrow(QueryException::class, 'Duplicate entry');
});

test('archiving resources and products preserves booking snapshots and ledger history', function () {
    $this->prepareStudio();
    $booking = Booking::factory()->create();
    $grant = $booking->grant;
    $grant->trainingTypes()->attach($booking->session->training_type_id);
    $entry = CreditEntry::factory()->debit($booking)->create();
    $snapshot = $grant->product_snapshot;
    $rules = $booking->rules_snapshot;

    foreach ([$booking->session->trainingType, $booking->session->room, $booking->session->instructor, $grant->product] as $catalogueRecord) {
        $catalogueRecord->update(['archived_at' => '2026-10-13 10:00:00']);
    }
    $grant->product->update(['name' => 'New offer', 'price_cents' => 9999, 'credits' => 10]);

    expect($booking->fresh()->rules_snapshot)->toEqual($rules);
    expect($grant->fresh()->product_snapshot)->toEqual($snapshot);
    expect($grant->trainingTypes()->count())->toBe(1);
    expect($booking->entries()->sum('amount'))->toBe('-1');
    $this->assertModelExists($booking);
    $this->assertModelExists($entry);

    expect(fn () => DB::table('package_products')->where('id', $grant->package_product_id)->delete())
        ->toThrow(QueryException::class, 'foreign key constraint fails');
    expect(fn () => DB::table('class_sessions')->where('id', $booking->class_session_id)->delete())
        ->toThrow(QueryException::class, 'foreign key constraint fails');
});

test('ledger entries reject model and bulk mutations', function (string $mutation) {
    $this->prepareStudio();
    $entry = CreditEntry::factory()->create();

    $query = CreditEntry::query()->whereKey($entry->id);
    expect(fn () => match ($mutation) {
        'save' => $entry->forceFill(['amount' => 999])->save(),
        'delete' => $entry->delete(),
        'bulk update' => $query->update(['amount' => 999]),
        'bulk delete' => $query->delete(),
        'force delete' => $query->forceDelete(),
        'upsert' => $query->upsert([$entry->getAttributes()], ['id']),
        'increment' => $query->increment('amount'),
        'decrement' => $query->decrement('amount'),
        'increment each' => $query->incrementEach(['amount' => 1]),
        'decrement each' => $query->decrementEach(['amount' => 1]),
        'touch' => $query->touch(),
    })->toThrow(LogicException::class, 'append-only');
    expect($entry->fresh()->amount)->toBe(5);
})->with(['save', 'delete', 'bulk update', 'bulk delete', 'force delete', 'upsert', 'increment', 'decrement', 'increment each', 'decrement each', 'touch']);

test('ledger booking and reversal must belong to the credited grant', function (string $column) {
    $this->prepareStudio();
    $booking = Booking::factory()->create();
    $debit = CreditEntry::factory()->debit($booking)->create();
    $otherBooking = Booking::factory()->create();
    $otherDebit = CreditEntry::factory()->debit($otherBooking)->create();
    $attributes = CreditEntry::factory()->reversal($debit)->make()->getAttributes();
    $attributes[$column] = $column === 'booking_id' ? $otherBooking->id : $otherDebit->id;

    expect(fn () => DB::table('credit_entries')->insert($attributes))
        ->toThrow(QueryException::class, 'foreign key constraint fails');
})->with(['booking_id', 'reverses_entry_id']);

test('ledger rejects foreign studio authors grants bookings and reversals', function (string $column, string $related) {
    $this->prepareStudio();
    $booking = Booking::factory()->create();
    $debit = CreditEntry::factory()->debit($booking)->create();
    $attributes = CreditEntry::factory()->reversal($debit)->make()->getAttributes();
    $otherStudio = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($otherStudio);
    $foreign = $related === User::class
        ? User::factory()->for($otherStudio)->create()
        : $related::factory()->create();
    $attributes[$column] = $foreign->id;

    expect(fn () => DB::table('credit_entries')->insert($attributes))
        ->toThrow(QueryException::class, 'foreign key constraint fails');
})->with([
    ['created_by_user_id', User::class], ['credit_grant_id', CreditGrant::class],
    ['booking_id', Booking::class], ['reverses_entry_id', CreditEntry::class],
]);

test('a debit can be reversed only once', function () {
    $this->prepareStudio();
    $debit = CreditEntry::factory()->debit(Booking::factory()->create())->create();
    CreditEntry::factory()->reversal($debit)->create();

    expect(fn () => CreditEntry::factory()->reversal($debit)->create())
        ->toThrow(QueryException::class, 'Duplicate entry');
});

test('operation keys reject repeated business records', function (string $model, string $key) {
    $this->prepareStudio();
    $original = $model::factory()->create([$key => 'same-business-operation']);

    expect(fn () => $model::factory()->create([$key => $original->$key]))
        ->toThrow(QueryException::class, 'Duplicate entry');
})->with([
    [ClassSession::class, 'operation_key'], [CreditGrant::class, 'operation_key'],
    [Booking::class, 'operation_key'], [CreditEntry::class, 'operation_key'],
    [OutboxEvent::class, 'deduplication_key'],
]);

test('different studios may use the same business operation key', function () {
    $this->prepareStudio();
    $first = CreditGrant::factory()->create(['operation_key' => 'import-001']);
    $otherStudio = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($otherStudio);

    $second = CreditGrant::factory()->create(['operation_key' => 'import-001']);

    expect($second->tenant_id)->not->toBe($first->tenant_id);
    $this->assertDatabaseCount('credit_grants', 2);
});

test('cancelled series occurrences cannot be generated again', function () {
    $this->prepareStudio();
    $series = ScheduleSeries::factory()->create();
    ClassSession::factory()->create([
        'schedule_series_id' => $series->id, 'occurrence_key' => '2026-10-12T18:00',
        'status' => 'cancelled', 'cancellation_reason' => 'holiday', 'cancelled_at' => '2026-10-09 10:00:00',
    ]);

    expect(fn () => ClassSession::factory()->create([
        'schedule_series_id' => $series->id, 'occurrence_key' => '2026-10-12T18:00',
    ]))->toThrow(QueryException::class, 'sessions_occurrence_unique');
});

test('attendance is unique and recording it does not debit a credit', function () {
    $this->prepareStudio();
    $booking = Booking::factory()->create();
    CreditEntry::factory()->debit($booking)->create();
    $attendance = AttendanceRecord::factory()->for($booking)->create();

    $attendance->forceFill([
        'status' => 'present', 'recorded_by_user_id' => $booking->session->instructor->user_id,
        'recorded_at' => '2026-10-12 16:00:00',
    ])->save();

    expect($booking->attendance->status)->toBe('present');
    expect($booking->entries()->count())->toBe(1);
    expect(fn () => AttendanceRecord::factory()->for($booking)->create())
        ->toThrow(QueryException::class, 'Duplicate entry');
});

test('allowed training types cannot link a product or grant across tenants', function (string $model) {
    $this->prepareStudio();
    $record = $model::factory()->create();
    $localType = TrainingType::factory()->create();
    $record->trainingTypes()->attach($localType);
    $otherStudio = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($otherStudio);
    $foreignType = TrainingType::factory()->create();
    app(TenantContext::class)->setTenant($this->studio);

    expect($record->trainingTypes->modelKeys())->toBe([$localType->id]);
    expect(fn () => $record->trainingTypes()->attach($foreignType->id))
        ->toThrow(QueryException::class, 'foreign key constraint fails');

    app(TenantContext::class)->setTenant($otherStudio);
    expect(fn () => $record->trainingTypes()->attach($foreignType->id))
        ->toThrow(QueryException::class, 'foreign key constraint fails');
})->with([PackageProduct::class, CreditGrant::class]);

test('external purchases and complimentary grants require their provenance', function (array $attributes) {
    $this->prepareStudio();

    expect(fn () => CreditGrant::factory()->create($attributes))
        ->toThrow(QueryException::class, 'credit_grants_documented_source');
})->with([
    [['source' => 'external_purchase', 'source_reference' => null]],
    [['source' => 'external_purchase', 'source_reference' => '   ']],
    [['source' => 'complimentary', 'reason' => null]],
    [['source' => 'complimentary', 'reason' => '   ']],
]);

test('documented grants keep sources separate from payments', function () {
    $this->prepareStudio();
    $external = CreditGrant::factory()->externalPurchase('External invoice 2026-42')->create();
    $complimentary = CreditGrant::factory()->complimentary('Scholarship')->create();

    expect($external->fresh())->source->toBe('external_purchase')->source_reference->toBe('External invoice 2026-42');
    expect($complimentary->fresh())->source->toBe('complimentary')->reason->toBe('Scholarship');
});

test('mysql rejects invalid periods quantities and state details', function (string $model, array $attributes, string $constraint) {
    $this->prepareStudio();

    expect(fn () => $model::factory()->create($attributes))
        ->toThrow(QueryException::class, $constraint);
})->with([
    'training duration' => [TrainingType::class, ['duration_minutes' => 0], 'positive_defaults'],
    'room capacity' => [Room::class, ['capacity' => 0], 'positive_capacity'],
    'rule horizon' => [BookingRuleVersion::class, ['booking_horizon_days' => 0], 'valid_version'],
    'series dates' => [ScheduleSeries::class, ['ends_on' => '2026-10-11'], 'valid_period'],
    'session interval' => [ClassSession::class, ['ends_at' => '2026-10-12 16:00:00'], 'valid_interval'],
    'availability weekday' => [AvailabilityRule::class, ['weekday' => 8], 'valid_window'],
    'availability step' => [AvailabilityRule::class, ['slot_step_minutes' => 0], 'valid_window'],
    'availability override' => [AvailabilityException::class, ['is_unavailable' => false], 'valid_override'],
    'product credits' => [PackageProduct::class, ['credits' => 0], 'positive_entitlement'],
    'product validity' => [PackageProduct::class, ['validity_days' => 0], 'positive_entitlement'],
    'grant validity' => [CreditGrant::class, ['expires_at' => '2026-10-09 10:00:00'], 'valid_entitlement'],
    'booking cancellation' => [Booking::class, ['status' => 'cancelled'], 'cancellation_details'],
    'booking cost' => [Booking::class, ['credits_used' => 2], 'one_credit'],
    'ledger empty movement' => [CreditEntry::class, ['amount' => 0], 'valid_movement'],
    'attendance actor' => [AttendanceRecord::class, ['status' => 'present'], 'recorded_actor'],
    'outbox version' => [OutboxEvent::class, ['aggregate_version' => 0], 'valid_subject'],
]);

test('prices and credits are integers and instants retain UTC independently of recurrence timezone', function () {
    $this->prepareStudio();
    $product = PackageProduct::factory()->create(['price_cents' => 12345]);
    $series = ScheduleSeries::factory()->create();
    $session = ClassSession::factory()->create(['schedule_series_id' => $series->id, 'occurrence_key' => '2026-10-12T18:00']);

    expect($product->fresh())->price_cents->toBe(12345)->credits->toBe(5)->currency->toBe('EUR');
    expect($series->fresh())->timezone->toBe('Europe/Zagreb')->local_start_time->toBe('18:00:00')->weekdays->toBe([1]);
    expect($session->fresh()->starts_at->toIso8601String())->toBe('2026-10-12T16:00:00+00:00');
});

test('the test seeder creates a connected tenant graph without changing the other studio', function () {
    $this->prepareStudio();
    $this->seed(TestingBookingFoundationSeeder::class);
    $firstBooking = Booking::query()->sole();
    $otherStudio = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($otherStudio);

    $this->seed(TestingBookingFoundationSeeder::class);

    $booking = Booking::query()->sole();
    expect($booking->tenant_id)->toBe($otherStudio->id);
    expect($booking->id)->not->toBe($firstBooking->id);
    expect($booking->session->series->tenant_id)->toBe($otherStudio->id);
    expect($booking->grant->trainingTypes()->sole()->id)->toBe($booking->session->training_type_id);
    expect($booking->grant->product->trainingTypes()->sole()->id)->toBe($booking->session->training_type_id);
    expect($booking->grant->entries()->sum('amount'))->toBe('4');
    expect($booking->attendance->status)->toBe('pending');
    expect(OutboxEvent::query()->sole()->recipient->id)->toBe($booking->user_id);
    expect(AvailabilityRule::query()->sole()->exceptions()->count())->toBe(1);
    $this->assertDatabaseCount('bookings', 2);
});

test('the fixture seeder cannot grant rights outside tests', function (string $environment) {
    $this->app->instance('env', $environment);

    expect(fn () => app(TestingBookingFoundationSeeder::class)->run())
        ->toThrow(LogicException::class, 'may only run in tests');
    $this->assertDatabaseEmpty('bookings');
})->with(['local', 'production']);

test('legacy or payment statuses cannot be used as booking scheduling or attendance statuses', function (string $model, string $status) {
    $this->prepareStudio();

    expect(fn () => $model::factory()->create(['status' => $status]))
        ->toThrow(QueryException::class, 'status');
})->with([
    [ClassSession::class, 'scheduled'],
    [Booking::class, 'pending_payment'],
    [AttendanceRecord::class, 'unmarked'],
]);
