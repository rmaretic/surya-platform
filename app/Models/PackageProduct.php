<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\TenantContext;
use Database\Factories\PackageProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 */
#[UseEloquentBuilder(TenantBuilder::class)]
#[Fillable(['name', 'price_cents', 'credits', 'validity_days', 'archived_at'])]
class PackageProduct extends Model
{
    /** @use HasFactory<PackageProductFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'credits' => 'integer',
            'validity_days' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return HasMany<CreditGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(CreditGrant::class, 'package_product_id');
    }

    /** @return BelongsToMany<TrainingType, $this> */
    public function trainingTypes(): BelongsToMany
    {
        return $this->belongsToMany(TrainingType::class, 'package_product_training_type', 'package_product_id')
            ->withPivotValue('tenant_id', app(TenantContext::class)->requireTenant()->id);
    }
}
