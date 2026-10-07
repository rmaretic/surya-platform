<?php

namespace App\Models;

use Database\Factories\PlatformAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['action', 'subject_type', 'subject_id', 'changes'])]
class PlatformAuditLog extends Model
{
    /** @use HasFactory<PlatformAuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function targetTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    /** @return BelongsTo<PlatformAdmin, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'actor_platform_admin_id');
    }
}
