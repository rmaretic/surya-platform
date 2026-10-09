<?php

namespace Database\Factories;

use App\Models\BookingRuleVersion;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\ScheduleSeries;
use App\Models\TrainingType;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduleSeries> */
class ScheduleSeriesFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'training_type_id' => TrainingType::factory(),
            'instructor_profile_id' => InstructorProfile::factory(),
            'room_id' => Room::factory(),
            'booking_rule_version_id' => BookingRuleVersion::factory(),
            'timezone' => 'Europe/Zagreb',
            'weekdays' => [1],
            'local_start_time' => '18:00:00',
            'starts_on' => '2026-10-12',
            'duration_minutes' => 60,
            'capacity' => 10,
        ];
    }
}
