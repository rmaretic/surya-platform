<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\TenantProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseEloquentBuilder(TenantBuilder::class)]
#[Fillable(['name', 'short_description', 'email', 'phone', 'address'])]
class TenantProfile extends Model
{
    /** @use HasFactory<TenantProfileFactory> */
    use BelongsToTenant, HasFactory;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
