<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $booking_id
 * @property int|null $class_session_id
 * @property int $recipient_user_id
 */
#[UseEloquentBuilder(TenantBuilder::class)]
#[Hidden(['payload'])]
class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'aggregate_version' => 'integer',
            'payload' => 'array',
            'available_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** @return BelongsTo<ClassSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
