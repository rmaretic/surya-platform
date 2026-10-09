<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\OutboxEvent;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OutboxEvent> */
class OutboxEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'booking_id' => Booking::factory(),
            'recipient_user_id' => fn (array $attributes): int => Booking::query()->whereKey($attributes['booking_id'])->sole()->user_id,
            'event_type' => 'booking.confirmed',
            'aggregate_version' => 1,
            'deduplication_key' => (string) Str::uuid(),
            'payload' => ['schema_version' => 1],
            'available_at' => '2026-10-09 10:00:00',
            'status' => 'pending',
        ];
    }
}
