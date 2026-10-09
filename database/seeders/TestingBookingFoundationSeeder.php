<?php

namespace Database\Seeders;

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
use App\Models\TrainingType;
use App\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Test fixtures only; this does not grant rights through the future booking service. */
class TestingBookingFoundationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('TestingBookingFoundationSeeder may only run in tests.');
        }

        app(TenantContext::class)->requireTenant();

        DB::transaction(function (): void {
            $type = TrainingType::factory()->create();
            $instructor = InstructorProfile::factory()->create();
            $room = Room::factory()->create();
            $rules = BookingRuleVersion::factory()->create();
            $resources = [
                'training_type_id' => $type->id,
                'instructor_profile_id' => $instructor->id,
                'room_id' => $room->id,
                'booking_rule_version_id' => $rules->id,
            ];
            $series = ScheduleSeries::factory()->create($resources);
            $session = ClassSession::factory()->create([
                ...$resources, 'schedule_series_id' => $series->id,
                'occurrence_key' => '2026-10-12T18:00', 'status' => 'published',
            ]);
            $availability = AvailabilityRule::factory()->create([
                ...$resources,
                'training_type_id' => TrainingType::factory()->create(['mode' => 'private', 'capacity' => 1])->id,
            ]);
            AvailabilityException::factory()->for($availability, 'rule')->create();

            $product = PackageProduct::factory()->create();
            $product->trainingTypes()->attach($type);
            $grant = CreditGrant::factory()->for($product, 'product')->create([
                'created_by_user_id' => $rules->created_by_user_id,
            ]);
            $grant->trainingTypes()->attach($type);
            CreditEntry::factory()->for($grant, 'grant')->create();
            $booking = Booking::factory()->for($grant, 'grant')->for($session, 'session')->create();
            CreditEntry::factory()->debit($booking)->create();
            AttendanceRecord::factory()->for($booking)->create();
            OutboxEvent::factory()->for($booking)->create();
        });
    }
}
