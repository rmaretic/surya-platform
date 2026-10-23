<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\CreditGrant;
use App\Models\InstructorProfile;
use App\Models\PackageProduct;
use App\Models\Room;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/** Imports artificial local history without changing the clock or sending notifications. */
class LocalBookingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('LocalBookingDemoSeeder may only run locally or in tests.');
        }
        try {
            DB::transaction(function (): void {
                $this->call(LocalDemoSeeder::class);
                foreach (['lotus', 'balance'] as $design) {
                    $tenant = Tenant::query()->where('design_key', $design)
                        ->whereHas('domains', fn ($query) => $query->withoutGlobalScope(TenantScope::class)->where('normalized_hostname', $design.'.yoga.test'))
                        ->lockForUpdate()->sole();
                    app(TenantContext::class)->setTenant($tenant);
                    if (TenantAuditLog::query()->where('action', 'demo.m2_seeded')->exists()) {
                        continue;
                    }
                    if (TrainingType::query()->exists() || InstructorProfile::query()->exists()
                        || Room::query()->exists() || PackageProduct::query()->exists() || BookingRuleVersion::query()->exists()) {
                        throw new LogicException('M2 demo requires an empty M2 catalog. Existing business data was preserved.');
                    }
                    $this->seedStudio($tenant);
                }
            }, 3);
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    private function seedStudio(Tenant $tenant): void
    {
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $day = $now->setTimezone($tenant->timezone)->startOfDay();
        $owner = User::query()->where('normalized_email', 'owner.'.$tenant->design_key.'@example.test')->sole();
        $customer = User::query()->where('normalized_email', 'customer@example.test')->sole();
        if (User::query()->where('normalized_email', 'customer2@example.test')->exists()) {
            throw new LogicException('The second demo customer already exists. No changes were saved.');
        }
        $second = User::query()->create([
            'email' => 'customer2@example.test', 'name' => ucfirst($tenant->design_key).' demo customer 2',
            'password' => ucfirst($tenant->design_key).'-Demo-2026!', 'role' => 'customer',
            'is_active' => true, 'email_verified_at' => $now,
        ]);
        $group = TrainingType::query()->create(['name' => 'DEMO grupa', 'mode' => 'group', 'duration_minutes' => 60, 'capacity' => 2]);
        $private = TrainingType::query()->create(['name' => 'DEMO privatni', 'mode' => 'private', 'duration_minutes' => 60, 'capacity' => 1]);
        $room = Room::query()->create(['name' => 'DEMO dvorana', 'capacity' => 6]);
        $privateRoom = Room::query()->create(['name' => 'DEMO privatna soba', 'capacity' => 1]);
        $profiles = [];
        foreach (['instructor', 'manager'] as $role) {
            $user = User::query()->where('normalized_email', $role.'.'.$tenant->design_key.'@example.test')->sole();
            $profiles[] = InstructorProfile::query()->create(['user_id' => $user->id, 'display_name' => $user->name]);
        }
        $rules = BookingRuleVersion::query()->create([
            'version' => 1, 'created_by_user_id' => $owner->id, 'minimum_notice_minutes' => 120,
            'booking_horizon_days' => 56, 'group_cancellation_minutes' => 720,
            'private_cancellation_minutes' => 1440, 'studio_refund_validity_days' => 7,
        ]);
        $product = PackageProduct::query()->create([
            'name' => 'DEMO 5 grupnih', 'price_cents' => 5000, 'currency' => 'EUR', 'credits' => 5, 'validity_days' => 45,
        ]);
        $product->trainingTypes()->attach($group->id);
        $larger = PackageProduct::query()->create([
            'name' => 'DEMO 10 grupnih', 'price_cents' => 9000, 'currency' => 'EUR', 'credits' => 10, 'validity_days' => 90,
        ]);
        $larger->trainingTypes()->attach($group->id);
        $privateProduct = PackageProduct::query()->create([
            'name' => 'DEMO 5 privatnih', 'price_cents' => 15000, 'currency' => 'EUR', 'credits' => 5, 'validity_days' => 45,
        ]);
        $privateProduct->trainingTypes()->attach($private->id);
        $active = $this->grant($customer, $owner, $product, $now);
        $secondGrant = $this->grant($second, $owner, $larger, $now);
        $this->grant($customer, $owner, $privateProduct, $now);
        $this->grant($second, $owner, $privateProduct, $now);
        $spent = $this->grant($customer, $owner, $product, $now->subDays(10));
        $this->grant($customer, $owner, $product, $now->subDays(60));
        $resources = ['training_type_id' => $group->id, 'room_id' => $room->id,
            'instructor_profile_id' => $profiles[0]->id, 'booking_rule_version_id' => $rules->id];
        $sessions = [];
        foreach (['pending' => -1, 'full' => 2, 'last-place' => 3, 'free' => 4, 'cancelled' => 5] as $label => $offset) {
            $start = $day->addDays($offset)->setTime(18, 0)->utc();
            $sessions[$label] = ClassSession::query()->create([
                ...$resources,
                'starts_at' => $start, 'ends_at' => $start->addHour(), 'capacity' => $label === 'full' ? 1 : 2,
                'status' => 'published', 'version' => 1,
            ]);
        }
        $this->booking($spent, $sessions['pending'], $rules, $now->subDays(2));
        foreach (range(2, 5) as $daysAgo) {
            $start = $day->subDays($daysAgo)->setTime(18, 0)->utc();
            $past = ClassSession::query()->create([...$resources,
                'starts_at' => $start, 'ends_at' => $start->addHour(), 'capacity' => 2,
                'status' => 'published', 'version' => 1]);
            $this->booking($spent, $past, $rules, $now->subDays(6));
        }
        $this->booking($secondGrant, $sessions['full'], $rules, $now);
        $this->booking($secondGrant, $sessions['last-place'], $rules, $now);
        $cancelled = $this->booking($active, $sessions['cancelled'], $rules, $now);
        $cancelled->forceFill(['status' => 'cancelled', 'cancelled_at' => $now,
            'cancelled_by_user_id' => $customer->id, 'cancellation_reason' => 'customer_timely'])->save();
        $debit = $cancelled->entries()->where('kind', 'debit')->sole();
        CreditEntry::query()->create([
            'credit_grant_id' => $active->id, 'booking_id' => $cancelled->id, 'reverses_entry_id' => $debit->id,
            'created_by_user_id' => $customer->id, 'kind' => 'reversal', 'amount' => 1,
            'occurred_at' => $now, 'operation_key' => 'demo-reversal:'.$cancelled->operation_key,
        ]);
        foreach (range(1, 7) as $weekday) {
            AvailabilityRule::query()->create([
                'training_type_id' => $private->id, 'room_id' => $privateRoom->id,
                'instructor_profile_id' => $profiles[1]->id, 'booking_rule_version_id' => $rules->id,
                'timezone' => $tenant->timezone, 'weekday' => $weekday,
                'local_start_time' => '09:00:00', 'local_end_time' => '13:00:00',
                'starts_on' => $day->toDateString(), 'ends_on' => $day->addDays(55)->toDateString(),
                'duration_minutes' => 60, 'slot_step_minutes' => 30,
                'buffer_before_minutes' => 15, 'buffer_after_minutes' => 15,
            ]);
        }
        TenantAuditLog::query()->create([
            'actor_user_id' => $owner->id, 'action' => 'demo.m2_seeded', 'subject_type' => 'tenants',
            'subject_id' => $tenant->id, 'changes' => ['seeded_at' => $now->toIso8601String(),
                'source' => 'artificial_local_fixture', 'version' => 1],
        ]);
    }

    private function grant(User $customer, User $owner, PackageProduct $product, CarbonImmutable $date): CreditGrant
    {
        $grant = CreditGrant::query()->create([
            'user_id' => $customer->id, 'package_product_id' => $product->id, 'created_by_user_id' => $owner->id,
            'source' => 'demo', 'source_reference' => 'M2-15 artificial local fixture',
            'reason' => 'Umjetni demo podaci; bez naplate.', 'operation_key' => (string) Str::uuid(),
            'granted_at' => $date, 'valid_from' => $date,
            'expires_at' => $date->setTimezone(app(TenantContext::class)->requireTenant()->timezone)->addDays($product->validity_days)->utc(),
            'credits' => $product->credits,
            'product_snapshot' => [...$product->only(['name', 'credits', 'price_cents', 'currency', 'validity_days']),
                'timezone' => app(TenantContext::class)->requireTenant()->timezone],
        ]);
        $grant->trainingTypes()->attach($product->trainingTypes()->pluck('training_types.id')->all());
        CreditEntry::query()->create([
            'credit_grant_id' => $grant->id, 'created_by_user_id' => $owner->id,
            'kind' => 'grant', 'amount' => $grant->credits, 'occurred_at' => $date, 'operation_key' => 'grant:'.$grant->operation_key,
        ]);

        return $grant;
    }

    private function booking(CreditGrant $grant, ClassSession $session, BookingRuleVersion $rules, CarbonImmutable $date): Booking
    {
        $booking = Booking::query()->create([
            'user_id' => $grant->user_id, 'created_by_user_id' => $grant->user_id, 'credit_grant_id' => $grant->id,
            'class_session_id' => $session->id, 'booking_rule_version_id' => $rules->id,
            'status' => 'confirmed', 'confirmed_at' => $date, 'credits_used' => 1, 'operation_key' => (string) Str::uuid(),
            'rules_snapshot' => [...$rules->only(['minimum_notice_minutes', 'booking_horizon_days',
                'group_cancellation_minutes', 'private_cancellation_minutes', 'studio_refund_validity_days']),
                'timezone' => app(TenantContext::class)->requireTenant()->timezone],
        ]);
        CreditEntry::query()->create([
            'credit_grant_id' => $grant->id, 'booking_id' => $booking->id, 'created_by_user_id' => $grant->user_id,
            'kind' => 'debit', 'amount' => -1, 'occurred_at' => $date, 'operation_key' => 'debit:'.$booking->operation_key,
        ]);
        if ($session->ends_at->lt(now())) {
            AttendanceRecord::query()->create(['booking_id' => $booking->id, 'status' => 'pending']);
        }

        return $booking;
    }
}
