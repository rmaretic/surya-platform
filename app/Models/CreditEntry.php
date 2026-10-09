<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\CreditEntryFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $credit_grant_id
 * @property int|null $booking_id
 * @property int $created_by_user_id
 * @property int|null $reverses_entry_id
 */
#[UseEloquentBuilder(CreditEntryBuilder::class)]
class CreditEntry extends Model
{
    /** @use HasFactory<CreditEntryFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return BelongsTo<CreditGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(CreditGrant::class, 'credit_grant_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<CreditEntry, $this> */
    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(CreditEntry::class, 'reverses_entry_id');
    }
}
