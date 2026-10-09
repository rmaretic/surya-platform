<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $class_session_id
 * @property int $credit_grant_id
 * @property int $booking_rule_version_id
 * @property int $created_by_user_id
 * @property int|null $cancelled_by_user_id
 * @property array{minimum_notice_minutes: int, booking_horizon_days: int, group_cancellation_minutes: int, private_cancellation_minutes: int, studio_refund_validity_days: int, timezone: string} $rules_snapshot
 */
#[UseEloquentBuilder(TenantBuilder::class)]
#[Hidden(['active_slot'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'rules_snapshot' => 'array',
            'credits_used' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<ClassSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /** @return BelongsTo<CreditGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(CreditGrant::class, 'credit_grant_id');
    }

    /** @return BelongsTo<BookingRuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(BookingRuleVersion::class, 'booking_rule_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @return HasMany<CreditEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CreditEntry::class, 'booking_id');
    }

    /** @return HasOne<AttendanceRecord, $this> */
    public function attendance(): HasOne
    {
        return $this->hasOne(AttendanceRecord::class, 'booking_id');
    }
}
