<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\InstructorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 */
#[UseEloquentBuilder(TenantBuilder::class)]
#[Fillable(['display_name', 'biography', 'archived_at'])]
class InstructorProfile extends Model
{
    /** @use HasFactory<InstructorProfileFactory> */
    use BelongsToTenant, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archived_at' => 'immutable_datetime',
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

    /** @return HasMany<ClassSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'instructor_profile_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if ($profile->archived_at === null && (! $profile->exists || $profile->isDirty(['user_id', 'archived_at']))) {
                if (! User::query()->whereKey($profile->user_id)->where('is_active', true)->exists()) {
                    throw new InvalidArgumentException('An instructor profile requires an active user in this studio.');
                }
            }
        });
    }
}
