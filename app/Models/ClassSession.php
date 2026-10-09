<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\ClassSessionFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $training_type_id
 * @property int $instructor_profile_id
 * @property int $room_id
 * @property int $booking_rule_version_id
 * @property int|null $schedule_series_id
 */
#[UseEloquentBuilder(TenantBuilder::class)]
class ClassSession extends Model
{
    /** @use HasFactory<ClassSessionFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'capacity' => 'integer',
            'version' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return BelongsTo<TrainingType, $this> */
    public function trainingType(): BelongsTo
    {
        return $this->belongsTo(TrainingType::class, 'training_type_id');
    }

    /** @return BelongsTo<InstructorProfile, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(InstructorProfile::class, 'instructor_profile_id');
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    /** @return BelongsTo<BookingRuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(BookingRuleVersion::class, 'booking_rule_version_id');
    }

    /** @return BelongsTo<ScheduleSeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(ScheduleSeries::class, 'schedule_series_id');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'class_session_id');
    }
}
