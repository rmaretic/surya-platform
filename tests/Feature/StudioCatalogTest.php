<?php

use App\Actions\ManageStudioCatalog;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\CreditGrant;
use App\Models\InstructorProfile;
use App\Models\PackageProduct;
use App\Models\PlatformAdmin;
use App\Models\Room;
use App\Models\ScheduleSeries;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

function catalogActor(string $role = 'owner'): User
{
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'lotus.yoga.test', 'is_primary' => true]);
    TenantProfile::factory()->for($tenant)->create();

    return User::factory()->for($tenant)->withTwoFactor()->create(['role' => $role]);
}

function catalogTypeData(array $overrides = []): array
{
    return [...['name' => 'Hatha', 'mode' => 'group', 'duration_minutes' => 60, 'capacity' => 12, 'description' => 'Javni opis', 'is_active' => true], ...$overrides];
}

test('catalog permissions distinguish owners managers instructors customers and platform admins', function (string $role, bool $manage, bool $pricing) {
    $actor = catalogActor($role);

    expect(Gate::forUser($actor)->allows('studio-catalog'))->toBe($manage);
    expect(Gate::forUser($actor)->allows('studio-pricing'))->toBe($pricing);
    $actor->is_active = false;
    expect(Gate::forUser($actor)->allows('studio-catalog'))->toBeFalse();
})->with([['owner', true, true], ['manager', true, false], ['instructor', false, false], ['customer', false, false]]);

test('catalog rejects foreign identities and platform admins at the gate', function () {
    $owner = catalogActor();
    app(TenantContext::class)->setTenant(Tenant::factory()->active()->create());
    expect(Gate::forUser($owner)->allows('studio-catalog'))->toBeFalse();
    expect(Gate::forUser(PlatformAdmin::factory()->create())->allows('studio-pricing'))->toBeFalse();
});

test('catalog requires authentication completed owner two factor and a permitted role', function () {
    $owner = catalogActor();
    $instructor = User::factory()->for($owner->tenant)->create(['role' => 'instructor']);
    $this->get('http://lotus.yoga.test/studio/catalog')->assertRedirect('http://lotus.yoga.test/login');
    $this->postJson('http://lotus.yoga.test/studio/catalog/rooms', [])->assertUnauthorized();
    $this->signInToStudio($instructor);
    $this->get('http://lotus.yoga.test/studio/catalog')->assertForbidden();
    $this->postJson('http://lotus.yoga.test/studio/catalog/training-types', catalogTypeData())->assertForbidden();
    $this->assertDatabaseCount('training_types', 0);
});

test('owner without two factor cannot change the catalog', function () {
    $owner = catalogActor();
    app(TenantContext::class)->setTenant($owner->tenant);
    $owner->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
    $this->signInToStudio($owner);

    $this->post('http://lotus.yoga.test/studio/catalog/training-types', catalogTypeData())->assertRedirect('http://lotus.yoga.test/settings/two-factor');
    $this->assertDatabaseCount('training_types', 0);
});

test('manager creates and edits catalog defaults without changing existing sessions', function () {
    $actor = catalogActor('manager');
    $type = TrainingType::factory()->create(['duration_minutes' => 60, 'capacity' => 10]);
    $session = ClassSession::factory()->create(['training_type_id' => $type->id, 'capacity' => 10]);
    $this->signInToStudio($actor);

    $this->post('http://lotus.yoga.test/studio/catalog/training-types', catalogTypeData())->assertRedirect();
    $this->patch("http://lotus.yoga.test/studio/catalog/training-types/{$type->id}", catalogTypeData(['duration_minutes' => 90, 'capacity' => 8, 'is_active' => false]))->assertRedirect();
    $this->post('http://lotus.yoga.test/studio/catalog/rooms', ['name' => 'Velika dvorana', 'capacity' => 20, 'is_active' => true])->assertRedirect();
    $this->post('http://lotus.yoga.test/studio/catalog/instructors', ['user_id' => $actor->id, 'display_name' => 'Javno ime', 'biography' => 'Javna biografija', 'is_active' => true])->assertRedirect();

    $this->assertDatabaseHas('training_types', ['id' => $type->id, 'duration_minutes' => 90, 'capacity' => 8]);
    expect(DB::table('training_types')->where('id', $type->id)->value('archived_at'))->not->toBeNull();
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'starts_at' => $session->starts_at, 'ends_at' => $session->ends_at, 'capacity' => 10]);
    $this->assertDatabaseHas('rooms', ['tenant_id' => $actor->tenant_id, 'name' => 'Velika dvorana', 'capacity' => 20]);
    $this->assertDatabaseHas('instructor_profiles', ['tenant_id' => $actor->tenant_id, 'user_id' => $actor->id, 'display_name' => 'Javno ime']);
    $this->assertDatabaseHas('users', ['id' => $actor->id, 'role' => 'manager']);
    $this->assertDatabaseCount('tenant_audit_logs', 4);
});

test('manager cannot submit owner fields on catalog routes', function (string $field) {
    $actor = catalogActor('manager');
    $this->signInToStudio($actor);

    $this->postJson('http://lotus.yoga.test/studio/catalog/training-types', catalogTypeData([$field => 10]))->assertForbidden();
    $this->assertDatabaseCount('training_types', 0);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
})->with(['price_cents', 'credits', 'validity_days', 'minimum_notice_minutes', 'booking_horizon_days', 'group_cancellation_minutes', 'private_cancellation_minutes', 'studio_refund_validity_days']);

test('manager cannot mutate prices or versioned rules directly', function () {
    $actor = catalogActor('manager');
    $package = PackageProduct::factory()->create();
    $this->signInToStudio($actor);

    $this->postJson('http://lotus.yoga.test/studio/catalog/packages', [])->assertForbidden();
    $this->patchJson("http://lotus.yoga.test/studio/catalog/packages/{$package->id}", ['price_cents' => 1])->assertForbidden();
    $this->postJson('http://lotus.yoga.test/studio/catalog/rules', ManageStudioCatalog::RULE_DEFAULTS)->assertForbidden();
    $this->assertDatabaseHas('package_products', ['id' => $package->id, 'price_cents' => $package->price_cents]);
    $this->assertDatabaseCount('booking_rule_versions', 0);
});

test('catalog reads and bindings are isolated to the current studio', function () {
    $owner = catalogActor();
    $local = TrainingType::factory()->create(['name' => 'Lokalno']);
    $foreignTenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($foreignTenant);
    $type = TrainingType::factory()->create(['name' => 'Tuđi trening']);
    $room = Room::factory()->create();
    $instructor = InstructorProfile::factory()->create();
    $package = PackageProduct::factory()->create();
    $this->signInToStudio($owner);

    $this->get('http://lotus.yoga.test/studio/catalog')->assertInertia(fn (Assert $page) => $page->component('studio/Catalog')->has('trainingTypes', 1)->where('trainingTypes.0.id', $local->id)->has('instructors', 0)->has('packages', 0)->has('rooms', 0));
    foreach (['training-types' => $type, 'rooms' => $room, 'instructors' => $instructor, 'packages' => $package] as $path => $record) {
        $this->patchJson("http://lotus.yoga.test/studio/catalog/{$path}/{$record->id}", [])->assertNotFound();
    }
    $this->postJson('http://lotus.yoga.test/studio/catalog/training-types', catalogTypeData(['tenant_id' => $foreignTenant->id]))->assertUnprocessable()->assertJsonValidationErrors('tenant_id');
    $this->assertDatabaseCount('tenant_audit_logs', 0);
});

test('instructor selection rejects foreign inactive customer and duplicate identities', function (string $kind) {
    $owner = catalogActor();
    $user = match ($kind) {
        'foreign' => null,
        'inactive' => User::factory()->for($owner->tenant)->create(['role' => 'instructor', 'is_active' => false]),
        'customer' => User::factory()->for($owner->tenant)->create(),
        default => $owner,
    };
    if ($kind === 'foreign') {
        $tenant = Tenant::factory()->active()->create();
        app(TenantContext::class)->setTenant($tenant);
        $user = User::factory()->for($tenant)->create(['role' => 'instructor']);
    }
    if ($kind === 'duplicate') {
        InstructorProfile::factory()->create(['user_id' => $owner->id]);
    }
    $this->signInToStudio($owner);

    $this->postJson('http://lotus.yoga.test/studio/catalog/instructors', ['user_id' => $user->id, 'display_name' => 'Instruktor', 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->assertDatabaseCount('tenant_audit_logs', 0);
})->with(['foreign', 'inactive', 'customer', 'duplicate']);

test('owner instructor profile preserves role and never leaks private staff data to public pages', function () {
    $owner = catalogActor();
    $this->signInToStudio($owner);

    $this->post('http://lotus.yoga.test/studio/catalog/instructors', ['user_id' => $owner->id, 'display_name' => 'Javno ime', 'biography' => '<script>alert(1)</script>', 'is_active' => true])->assertRedirect();
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner']);
    $this->get('http://lotus.yoga.test/studio/catalog')->assertInertia(fn (Assert $page) => $page->missing('staff.0.email')->missing('staff.0.password')->missing('instructors.0.user')->missing('instructors.0.tenant_id'));
    foreach (['/', '/about'] as $path) {
        $this->get('http://lotus.yoga.test'.$path)->assertInertia(fn (Assert $page) => $page->where('auth.user', null)->missing('staff')->missing('instructors')->missing('profile.users'));
    }
});

test('instructor cannot be archived or deactivated while responsible for sessions', function (string $status) {
    $this->freezeTime();
    $owner = catalogActor();
    $profile = InstructorProfile::factory()->create();
    ClassSession::factory()->create(['instructor_profile_id' => $profile->id, 'status' => $status, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $this->signInToStudio($owner);

    $this->patchJson("http://lotus.yoga.test/studio/catalog/instructors/{$profile->id}", ['user_id' => $profile->user_id, 'display_name' => $profile->display_name, 'is_active' => false])->assertUnprocessable()->assertInvalid(['is_active' => 'Prije deaktivacije zamijenite instruktora']);
    $this->postJson("http://lotus.yoga.test/studio/staff/{$profile->user_id}/deactivate", ['confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->assertDatabaseHas('instructor_profiles', ['id' => $profile->id, 'archived_at' => null]);
    $this->assertDatabaseHas('users', ['id' => $profile->user_id, 'is_active' => true]);
    $this->assertDatabaseCount('tenant_audit_logs', 0);
})->with(['draft', 'published']);

test('deactivation protects ongoing sessions and active recurring availability', function (string $kind) {
    $this->freezeTime();
    $owner = catalogActor();
    $profile = InstructorProfile::factory()->create();
    match ($kind) {
        'ongoing' => ClassSession::factory()->create(['instructor_profile_id' => $profile->id, 'status' => 'published', 'starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30)]),
        'series' => ScheduleSeries::factory()->create(['instructor_profile_id' => $profile->id, 'ends_on' => null]),
        'availability' => AvailabilityRule::factory()->create(['instructor_profile_id' => $profile->id, 'ends_on' => null]),
    };
    $this->signInToStudio($owner);

    $this->postJson("http://lotus.yoga.test/studio/staff/{$profile->user_id}/deactivate", ['confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->assertDatabaseHas('users', ['id' => $profile->user_id, 'is_active' => true]);
})->with(['ongoing', 'series', 'availability']);

test('deactivation preserves past and cancelled sessions and archives the profile', function () {
    $this->freezeTime();
    $owner = catalogActor();
    $profile = InstructorProfile::factory()->create();
    ClassSession::factory()->create(['instructor_profile_id' => $profile->id, 'status' => 'completed', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
    ClassSession::factory()->create(['instructor_profile_id' => $profile->id, 'status' => 'cancelled', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $this->signInToStudio($owner);

    $this->post("http://lotus.yoga.test/studio/staff/{$profile->user_id}/deactivate", ['confirmed' => true])->assertRedirect();
    $this->assertDatabaseHas('users', ['id' => $profile->user_id, 'is_active' => false]);
    expect(DB::table('instructor_profiles')->where('id', $profile->id)->value('archived_at'))->not->toBeNull();
    $this->assertDatabaseCount('class_sessions', 2);
});

test('new rule versions preserve old bookings sessions and other studio rules', function () {
    $owner = catalogActor();
    $rules = BookingRuleVersion::factory()->create(['version' => 1, 'created_by_user_id' => $owner->id]);
    $session = ClassSession::factory()->create(['booking_rule_version_id' => $rules->id]);
    $booking = Booking::factory()->create(['class_session_id' => $session->id]);
    $before = DB::table('bookings')->where('id', $booking->id)->first();
    $other = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($other);
    $foreign = BookingRuleVersion::factory()->create(['version' => 1]);
    $this->signInToStudio($owner);

    $this->post('http://lotus.yoga.test/studio/catalog/rules', [...ManageStudioCatalog::RULE_DEFAULTS, 'group_cancellation_minutes' => 1440])->assertRedirect();
    $this->post('http://lotus.yoga.test/studio/catalog/rules', [...ManageStudioCatalog::RULE_DEFAULTS, 'group_cancellation_minutes' => 2880])->assertRedirect();

    $this->assertDatabaseHas('booking_rule_versions', ['tenant_id' => $owner->tenant_id, 'version' => 3, 'group_cancellation_minutes' => 2880, 'created_by_user_id' => $owner->id]);
    $this->assertDatabaseHas('booking_rule_versions', ['id' => $rules->id, 'group_cancellation_minutes' => 720]);
    $this->assertDatabaseHas('booking_rule_versions', ['id' => $foreign->id, 'version' => 1, 'group_cancellation_minutes' => 720]);
    expect(DB::table('bookings')->where('id', $booking->id)->first())->toEqual($before);
    $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'booking_rule_version_id' => $rules->id]);
    $this->get('http://lotus.yoga.test/studio/catalog')->assertInertia(fn (Assert $page) => $page->where('rules.version', 3)->where('rules.group_cancellation_minutes', 2880));
});

test('owner edits package prices and coverage without rewriting previously granted rights', function () {
    $owner = catalogActor();
    $type = TrainingType::factory()->create();
    $package = PackageProduct::factory()->create();
    $grant = CreditGrant::factory()->create(['package_product_id' => $package->id]);
    $before = DB::table('credit_grants')->where('id', $grant->id)->first();
    $this->signInToStudio($owner);

    $this->patch("http://lotus.yoga.test/studio/catalog/packages/{$package->id}", ['name' => 'Novi paket', 'price_cents' => 7500, 'credits' => 10, 'validity_days' => 90, 'training_type_ids' => [$type->id], 'is_active' => false])->assertRedirect();

    $this->assertDatabaseHas('package_products', ['id' => $package->id, 'price_cents' => 7500, 'credits' => 10]);
    $this->assertDatabaseHas('package_product_training_type', ['tenant_id' => $owner->tenant_id, 'package_product_id' => $package->id, 'training_type_id' => $type->id]);
    expect(DB::table('credit_grants')->where('id', $grant->id)->first())->toEqual($before);
});

test('package coverage rejects foreign training types without changing the package', function () {
    $owner = catalogActor();
    $package = PackageProduct::factory()->create();
    app(TenantContext::class)->setTenant(Tenant::factory()->active()->create());
    $foreign = TrainingType::factory()->create();
    $this->signInToStudio($owner);

    $this->patchJson("http://lotus.yoga.test/studio/catalog/packages/{$package->id}", ['name' => 'Injected', 'price_cents' => 1, 'credits' => 1, 'validity_days' => 1, 'training_type_ids' => [$foreign->id], 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('training_type_ids.0');
    $this->assertDatabaseHas('package_products', ['id' => $package->id, 'name' => $package->name]);
    $this->assertDatabaseCount('package_product_training_type', 0);
});

test('training type validation rejects invalid bounds and private group sizes', function (array $invalid, string $field) {
    $owner = catalogActor();
    $this->signInToStudio($owner);

    $this->postJson('http://lotus.yoga.test/studio/catalog/training-types', catalogTypeData($invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('training_types', 0);
})->with([
    [['name' => ''], 'name'], [['duration_minutes' => 0], 'duration_minutes'], [['duration_minutes' => 481], 'duration_minutes'],
    [['capacity' => 0], 'capacity'], [['capacity' => 501], 'capacity'], [['capacity' => 1.5], 'capacity'],
    [['mode' => 'unknown'], 'mode'], [['mode' => 'private', 'capacity' => 2], 'capacity'],
]);

test('rules reject invalid limits and empty booking windows without creating a version', function (array $invalid, string $field) {
    $owner = catalogActor();
    $this->signInToStudio($owner);

    $this->postJson('http://lotus.yoga.test/studio/catalog/rules', [...ManageStudioCatalog::RULE_DEFAULTS, ...$invalid])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('booking_rule_versions', 0);
})->with([
    [['minimum_notice_minutes' => 0], 'minimum_notice_minutes'], [['minimum_notice_minutes' => 1440, 'booking_horizon_days' => 1], 'minimum_notice_minutes'],
    [['booking_horizon_days' => 366], 'booking_horizon_days'], [['group_cancellation_minutes' => -1], 'group_cancellation_minutes'],
    [['private_cancellation_minutes' => 525601], 'private_cancellation_minutes'], [['studio_refund_validity_days' => 6], 'studio_refund_validity_days'],
    [['version' => 999], 'version'],
]);

test('room capacity cannot invalidate a future session', function () {
    $owner = catalogActor();
    $room = Room::factory()->create(['capacity' => 20]);
    ClassSession::factory()->create(['room_id' => $room->id, 'capacity' => 15, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $this->signInToStudio($owner);

    $this->patchJson("http://lotus.yoga.test/studio/catalog/rooms/{$room->id}", ['name' => $room->name, 'capacity' => 14, 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('capacity');
    $this->assertDatabaseHas('rooms', ['id' => $room->id, 'capacity' => 20]);
});

test('archived profile of inactive staff can be edited but cannot be reactivated', function () {
    $owner = catalogActor();
    $staff = User::factory()->for($owner->tenant)->create(['role' => 'instructor', 'is_active' => false]);
    $profile = InstructorProfile::factory()->create(['user_id' => $staff->id, 'archived_at' => now()]);
    $this->signInToStudio($owner);

    $this->patch("http://lotus.yoga.test/studio/catalog/instructors/{$profile->id}", ['user_id' => $staff->id, 'display_name' => 'Ispravljeno ime', 'is_active' => false])->assertRedirect();
    $this->patchJson("http://lotus.yoga.test/studio/catalog/instructors/{$profile->id}", ['user_id' => $staff->id, 'display_name' => 'Ispravljeno ime', 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->assertDatabaseHas('instructor_profiles', ['id' => $profile->id, 'display_name' => 'Ispravljeno ime']);
    expect(DB::table('instructor_profiles')->where('id', $profile->id)->value('archived_at'))->not->toBeNull();
});

test('instructor identity and training mode cannot be reassigned to rewrite history', function () {
    $owner = catalogActor();
    $profile = InstructorProfile::factory()->create();
    $type = TrainingType::factory()->create(['mode' => 'group']);
    $this->signInToStudio($owner);

    $this->patchJson("http://lotus.yoga.test/studio/catalog/instructors/{$profile->id}", ['user_id' => $owner->id, 'display_name' => 'Premješten', 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->patchJson("http://lotus.yoga.test/studio/catalog/training-types/{$type->id}", catalogTypeData(['mode' => 'private', 'capacity' => 1]))->assertUnprocessable()->assertJsonValidationErrors('mode');
    $this->assertDatabaseHas('instructor_profiles', ['id' => $profile->id, 'user_id' => $profile->user_id]);
    $this->assertDatabaseHas('training_types', ['id' => $type->id, 'mode' => 'group']);
});

test('owner creates the first rules and package with tenant owned coverage', function () {
    $owner = catalogActor();
    $type = TrainingType::factory()->create();
    $this->signInToStudio($owner);

    $this->post('http://lotus.yoga.test/studio/catalog/rules', ManageStudioCatalog::RULE_DEFAULTS)->assertRedirect();
    $this->post('http://lotus.yoga.test/studio/catalog/packages', ['name' => 'Pet dolazaka', 'price_cents' => 5000, 'credits' => 5, 'validity_days' => 45, 'training_type_ids' => [$type->id], 'is_active' => true])->assertRedirect();
    $this->assertDatabaseHas('booking_rule_versions', ['tenant_id' => $owner->tenant_id, 'version' => 1, 'created_by_user_id' => $owner->id]);
    $this->assertDatabaseHas('package_products', ['tenant_id' => $owner->tenant_id, 'name' => 'Pet dolazaka', 'price_cents' => 5000]);
    $this->assertDatabaseHas('package_product_training_type', ['tenant_id' => $owner->tenant_id, 'training_type_id' => $type->id]);
    $this->assertDatabaseCount('credit_grants', 0);
});

test('package values reject nonpositive fractional and excessive values', function (string $field, mixed $value) {
    $owner = catalogActor();
    $type = TrainingType::factory()->create();
    $this->signInToStudio($owner);

    $data = ['name' => 'Paket', 'price_cents' => 5000, 'credits' => 5, 'validity_days' => 45, 'training_type_ids' => [$type->id], 'is_active' => true];
    $data[$field] = $value;
    $this->postJson('http://lotus.yoga.test/studio/catalog/packages', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('package_products', 0);
})->with([['price_cents', 0], ['price_cents', 1.5], ['price_cents', 10000001], ['credits', 0], ['credits', 1001], ['validity_days', 0], ['validity_days', 3651], ['training_type_ids', []]]);

test('manager can archive and restore instructors and rooms without deleting history', function () {
    $actor = catalogActor('manager');
    $profile = InstructorProfile::factory()->create();
    $room = Room::factory()->create();
    $this->signInToStudio($actor);

    foreach ([false, true] as $active) {
        $this->patch("http://lotus.yoga.test/studio/catalog/instructors/{$profile->id}", ['user_id' => $profile->user_id, 'display_name' => 'Instruktor', 'is_active' => $active])->assertRedirect();
        $this->patch("http://lotus.yoga.test/studio/catalog/rooms/{$room->id}", ['name' => 'Prostor', 'capacity' => 20, 'is_active' => $active])->assertRedirect();
    }
    $this->assertDatabaseHas('instructor_profiles', ['id' => $profile->id, 'archived_at' => null]);
    $this->assertDatabaseHas('rooms', ['id' => $room->id, 'archived_at' => null]);
    $this->assertDatabaseHas('users', ['id' => $profile->user_id, 'is_active' => true, 'role' => 'instructor']);
});
