<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceRecord> */
class AttendanceRecordFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'booking_id' => Booking::factory(),
            'status' => 'pending',
        ];
    }
}
