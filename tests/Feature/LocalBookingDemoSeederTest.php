<?php

use App\Actions\CreateBooking;
use App\Actions\ManageAttendance;
use App\Actions\ManageBooking;
use App\Actions\ManageStudioCancellation;
use App\Actions\ManageStudioCredits;
use App\Actions\ManageStudioSchedule;
use App\Actions\ReadOperationalDashboard;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditGrant;
use App\Models\PackageProduct;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalBookingDemoSeeder;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @return array<string, array<int, object>> */
function bookingDemoSnapshot(int $tenantId): array
{
    $snapshot = [];
    foreach (['users', 'training_types', 'instructor_profiles', 'rooms', 'booking_rule_versions',
        'class_sessions', 'availability_rules', 'package_products', 'package_product_training_type',
        'credit_grants', 'credit_grant_training_type', 'credit_entries', 'bookings', 'attendance_records',
        'outbox_events', 'tenant_audit_logs'] as $table) {
        $order = match ($table) {
            'package_product_training_type' => 'package_product_id',
            'credit_grant_training_type' => 'credit_grant_id',
            default => 'id',
        };
        $snapshot[$table] = DB::table($table)->where('tenant_id', $tenantId)->orderBy($order)->get()->all();
    }

    return $snapshot;
}

test('booking demo seeds complete isolated artificial histories without sending or queueing mail', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00 UTC'));
    Mail::fake();
    Notification::fake();
    Queue::fake();

    $this->seed(LocalBookingDemoSeeder::class);

    expect(app(TenantContext::class)->tenant())->toBeNull();
    $this->assertDatabaseCount('users', 10);
    $this->assertDatabaseCount('outbox_events', 0);
    foreach (Tenant::query()->get() as $tenant) {
        app(TenantContext::class)->setTenant($tenant);
        expect(TrainingType::query()->count())->toBe(2);
        expect(AvailabilityRule::query()->count())->toBe(7);
        expect(ClassSession::query()->count())->toBe(9);
        expect(Booking::query()->where('status', 'cancelled')->count())->toBe(1);
        $customer = User::query()->where('normalized_email', 'customer@example.test')->sole();
        $grants = CreditGrant::query()->where('user_id', $customer->id)->orderBy('granted_at')->get();
        expect($grants->map(fn ($grant) => (int) $grant->entries()->sum('amount'))->all())->toBe([5, 0, 5, 5]);
        expect($grants->first()->expires_at->isPast())->toBeTrue();
        foreach (CreditGrant::query()->with('entries', 'trainingTypes')->get() as $grant) {
            expect($grant->source)->toBe('demo');
            expect($grant->source_reference)->toBe('M2-15 artificial local fixture');
            expect($grant->product_snapshot['credits'])->toBe($grant->credits);
            expect($grant->entries->sum('amount'))->toBeGreaterThanOrEqual(0);
            expect($grant->trainingTypes->pluck('tenant_id')->unique()->all())->toBe([$tenant->id]);
        }
        $manager = User::query()->where('role', 'manager')->sole();
        $report = app(ReadOperationalDashboard::class)->studio($manager, ['from' => '2026-10-18', 'to' => '2026-10-30']);
        expect($report['summary'])->toMatchArray(['sessions' => 9, 'capacity' => 17, 'confirmed' => 7, 'pending' => 7]);
    }
    Mail::assertNothingSent();
    Notification::assertNothingSent();
    Queue::assertNothingPushed();
});

test('repeated booking seed preserves business changes credentials and dates after the demo ages', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00 UTC'));
    $this->seed(LocalBookingDemoSeeder::class);
    $tenant = Tenant::query()->where('design_key', 'lotus')->sole();
    app(TenantContext::class)->setTenant($tenant);
    $booking = Booking::query()->whereHas('session', fn ($query) => $query->where('starts_at', '2026-10-25 17:00:00'))->sole();
    app(ManageBooking::class)->handle($booking->user, $booking, ['operation_key' => (string) Str::uuid()]);
    User::query()->where('role', 'owner')->firstOrFail()->forceFill(['is_active' => false, 'password' => 'Edited-demo-password!'])->save();
    $before = Tenant::query()->get()->mapWithKeys(fn ($tenant) => [$tenant->id => bookingDemoSnapshot($tenant->id)])->all();
    $this->travel(100)->days();

    $this->seed(LocalBookingDemoSeeder::class);

    foreach ($before as $id => $snapshot) {
        expect(bookingDemoSnapshot($id))->toEqual($snapshot);
    }
    expect(app(TenantContext::class)->tenant())->toBeNull();
});

test('booking seed rejects nonlocal environments even when forced', function (string $environment) {
    app()->detectEnvironment(fn (): string => $environment);
    try {
        expect(fn () => $this->artisan('db:seed', ['--class' => LocalBookingDemoSeeder::class, '--force' => true])->run())
            ->toThrow(LogicException::class);
        $this->assertDatabaseCount('tenants', 0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
})->with(['production', 'staging']);

test('a conflict in the second studio rolls back the entire booking seed and clears context', function (string $conflict) {
    $this->seed(LocalDemoSeeder::class);
    $balance = Tenant::query()->where('design_key', 'balance')->sole();
    app(TenantContext::class)->setTenant($balance);
    if ($conflict === 'catalog') {
        TrainingType::factory()->create(['name' => 'Existing business data']);
    } else {
        User::factory()->for($balance)->create(['email' => 'customer2@example.test']);
    }
    $before = bookingDemoSnapshot($balance->id);

    expect(fn () => $this->seed(LocalBookingDemoSeeder::class))->toThrow(LogicException::class);

    expect(bookingDemoSnapshot($balance->id))->toEqual($before);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('credit_entries', 0);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
    expect(app(TenantContext::class)->tenant())->toBeNull();
})->with(['catalog', 'customer']);

test('seeded demo completes booking cancellation private conflict attendance and studio refund with isolated aggregates', function (string $design) {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00 UTC'));
    $this->seed(LocalBookingDemoSeeder::class);
    $tenant = Tenant::query()->where('design_key', $design)->sole();
    $other = Tenant::query()->where('id', '!=', $tenant->id)->sole();
    $otherBefore = bookingDemoSnapshot($other->id);
    app(TenantContext::class)->setTenant($tenant);
    $owner = User::query()->where('role', 'owner')->sole();
    $owner->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
    $customer = User::query()->where('normalized_email', 'customer@example.test')->sole();
    $second = User::query()->where('normalized_email', 'customer2@example.test')->sole();
    $template = ClassSession::query()->where('starts_at', '2026-10-27 17:00:00')->sole();
    app(ManageStudioSchedule::class)->saveSession($owner, new ClassSession, [
        ...$template->only(['training_type_id', 'instructor_profile_id', 'room_id']),
        'local_start' => '2026-10-29 18:00', 'duration_minutes' => 60, 'capacity' => 1, 'status' => 'published',
    ]);
    $session = ClassSession::query()->latest('id')->firstOrFail();
    $grant = app(ManageStudioCredits::class)->grant($owner, [
        'operation_key' => (string) Str::uuid(), 'user_id' => $customer->id,
        'package_product_id' => PackageProduct::query()->where('name', 'DEMO 5 grupnih')->sole()->id, 'source' => 'demo',
    ]);
    expect($grant->source)->toBe('demo');
    $create = app(CreateBooking::class);
    $request = ['operation_key' => (string) Str::uuid(), 'class_session_id' => $session->id];
    $booking = $create->handle($customer, $request);
    expect($create->handle($customer, $request)->id)->toBe($booking->id);
    $secondBalance = DB::table('credit_entries')->whereIn('credit_grant_id', CreditGrant::query()->where('user_id', $second->id)->pluck('id'))->sum('amount');
    expect(fn () => $create->handle($second, ['operation_key' => (string) Str::uuid(), 'class_session_id' => $session->id]))
        ->toThrow(ValidationException::class);
    expect($session->bookings()->where('status', 'confirmed')->count())->toBe(1);
    expect(DB::table('credit_entries')->whereIn('credit_grant_id', CreditGrant::query()->where('user_id', $second->id)->pluck('id'))->sum('amount'))->toBe($secondBalance);
    $cancel = ['operation_key' => (string) Str::uuid()];
    app(ManageBooking::class)->handle($customer, $booking, $cancel);
    app(ManageBooking::class)->handle($customer, $booking, $cancel);
    expect($booking->entries()->where('kind', 'reversal')->count())->toBe(1);
    expect((int) $booking->entries()->sum('amount'))->toBe(0);
    $availability = AvailabilityRule::query()->where('weekday', 5)->sole();
    $private = $create->handle($customer, ['operation_key' => (string) Str::uuid(),
        'availability_rule_id' => $availability->id, 'starts_at' => '2026-10-30T10:00:00+01:00']);
    expect(fn () => $create->handle($second, ['operation_key' => (string) Str::uuid(),
        'availability_rule_id' => $availability->id, 'starts_at' => '2026-10-30T10:30:00+01:00']))->toThrow(ValidationException::class);
    expect($private->session->bookings()->count())->toBe(1);
    $past = Booking::query()->whereHas('session', fn ($query) => $query->where('starts_at', '2026-10-22 16:00:00'))->sole();
    $instructor = User::query()->where('role', 'instructor')->sole();
    $ledgerBefore = $past->entries()->count();
    app(ManageAttendance::class)->handle($instructor, $past, ['operation_key' => (string) Str::uuid(), 'status' => 'present', 'expected_status' => 'pending']);
    expect($past->attendance()->sole()->status)->toBe('present');
    expect($past->entries()->count())->toBe($ledgerBefore);
    $full = ClassSession::query()->where('starts_at', '2026-10-25 17:00:00')->sole();
    $studioCancel = app(ManageStudioCancellation::class);
    $preview = $studioCancel->preview($owner, $full);
    $studioCancel->cancel($owner, $full, ['operation_key' => (string) Str::uuid(), 'preview_token' => $preview['preview_token'], 'reason' => 'Demo otkaz studija']);
    expect($full->fresh()->status)->toBe('cancelled');
    expect($full->bookings()->sole()->entries()->where('kind', 'reversal')->count())->toBe(1);
    expect(bookingDemoSnapshot($other->id))->toEqual($otherBefore);
    $admin = PlatformAdmin::query()->sole();
    $admin->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
    app(TenantContext::class)->setPlatform();
    $report = app(ReadOperationalDashboard::class)->platform($admin, ['from' => '2026-10-18', 'to' => '2026-10-30']);
    expect($report['summary'])->toMatchArray(['sessions' => 19, 'cancelled' => 1, 'capacity' => 35,
        'confirmed' => 14, 'pending' => 13, 'present' => 1]);
})->with(['lotus', 'balance']);
