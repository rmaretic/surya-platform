<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\BookingRuleVersion;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\TrainingType;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AvailabilityRule> */
class AvailabilityRuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'training_type_id' => TrainingType::factory()->state(['mode' => 'private', 'capacity' => 1]),
            'instructor_profile_id' => InstructorProfile::factory(),
            'room_id' => Room::factory(),
            'booking_rule_version_id' => BookingRuleVersion::factory(),
            'timezone' => 'Europe/Zagreb',
            'weekday' => 1,
            'local_start_time' => '09:00:00',
            'local_end_time' => '17:00:00',
            'starts_on' => '2026-10-12',
            'duration_minutes' => 60,
            'slot_step_minutes' => 30,
            'buffer_before_minutes' => 15,
            'buffer_after_minutes' => 15,
        ];
    }
}
