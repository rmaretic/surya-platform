<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 */
#[UseEloquentBuilder(TenantBuilder::class)]
#[Fillable(['name', 'capacity', 'archived_at'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return HasMany<ClassSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'room_id');
    }
}
