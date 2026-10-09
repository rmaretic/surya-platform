<?php

namespace Database\Factories;

use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\TrainingType;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClassSession> */
class ClassSessionFactory extends Factory
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
            'starts_at' => '2026-10-12 16:00:00',
            'ends_at' => '2026-10-12 17:00:00',
            'capacity' => 10,
            'status' => 'draft',
            'version' => 1,
        ];
    }
}
