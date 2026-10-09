<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\ScheduleSeriesFactory;
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
 */
#[UseEloquentBuilder(TenantBuilder::class)]
class ScheduleSeries extends Model
{
    /** @use HasFactory<ScheduleSeriesFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'schedule_series';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'archived_at' => 'immutable_datetime',
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

    /** @return HasMany<ClassSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'schedule_series_id');
    }
}
