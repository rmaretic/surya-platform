<?php

namespace App\Models;

use App\Actions\NormalizeHostname;
use App\Concerns\BelongsToTenant;
use Database\Factories\TenantDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseEloquentBuilder(TenantBuilder::class)]
#[Fillable(['hostname'])]
#[Hidden(['primary_tenant_id'])]
class TenantDomain extends Model
{
    /** @use HasFactory<TenantDomainFactory> */
    use BelongsToTenant, HasFactory;

    /** @return Attribute<string, string> */
    protected function hostname(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => app(NormalizeHostname::class)->handle($value));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime', 'is_active' => 'boolean', 'is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
