<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\BookingRuleVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $created_by_user_id
 */
#[UseEloquentBuilder(TenantBuilder::class)]
class BookingRuleVersion extends Model
{
    /** @use HasFactory<BookingRuleVersionFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'minimum_notice_minutes' => 'integer',
            'booking_horizon_days' => 'integer',
            'group_cancellation_minutes' => 'integer',
            'private_cancellation_minutes' => 'integer',
            'studio_refund_validity_days' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
