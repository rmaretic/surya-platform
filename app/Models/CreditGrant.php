<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\TenantContext;
use Database\Factories\CreditGrantFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $package_product_id
 * @property int $created_by_user_id
 * @property int|null $compensates_credit_entry_id
 * @property array{name: string, price_cents: int, currency: string, credits: int, validity_days: int, timezone: string} $product_snapshot
 */
#[UseEloquentBuilder(TenantBuilder::class)]
class CreditGrant extends Model
{
    /** @use HasFactory<CreditGrantFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'valid_from' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'credits' => 'integer',
            'product_snapshot' => 'array',
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

    /** @return BelongsTo<PackageProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PackageProduct::class, 'package_product_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<CreditEntry, $this> */
    public function compensatedEntry(): BelongsTo
    {
        return $this->belongsTo(CreditEntry::class, 'compensates_credit_entry_id');
    }

    /** @return HasMany<CreditEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CreditEntry::class, 'credit_grant_id');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'credit_grant_id');
    }

    /** @return BelongsToMany<TrainingType, $this> */
    public function trainingTypes(): BelongsToMany
    {
        return $this->belongsToMany(TrainingType::class, 'credit_grant_training_type', 'credit_grant_id')
            ->withPivotValue('tenant_id', app(TenantContext::class)->requireTenant()->id);
    }
}
