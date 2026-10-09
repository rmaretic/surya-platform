<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\AvailabilityExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $availability_rule_id
 */
#[UseEloquentBuilder(TenantBuilder::class)]
class AvailabilityException extends Model
{
    /** @use HasFactory<AvailabilityExceptionFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'local_date' => 'immutable_date',
            'is_unavailable' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return BelongsTo<AvailabilityRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AvailabilityRule::class, 'availability_rule_id');
    }
}
