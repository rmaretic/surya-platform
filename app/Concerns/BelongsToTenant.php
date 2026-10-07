<?php

namespace App\Concerns;

use App\Models\Scopes\TenantScope;
use App\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait BelongsToTenant
{
    /** @param array<array-key, mixed>|string $with */
    public function fresh($with = []): ?static
    {
        if (! $this->exists) {
            return null;
        }

        return $this->setKeysForSelectQuery($this->newQuery())
            ->useWritePdo()->with(is_string($with) ? func_get_args() : $with)->first();
    }

    /**
     * @param  Builder<static>  $query
     * @return $this
     */
    protected function refreshUsingQuery(Builder $query): static
    {
        $query->where($this->qualifyColumn('tenant_id'), app(TenantContext::class)->requireTenant()->id);

        return parent::refreshUsingQuery($query);
    }

    /**
     * Restore only inside an established context; queued jobs carry scalar IDs.
     *
     * @param  array<array-key, int|string>|int|string  $ids
     * @return Builder<static>
     */
    public function newQueryForRestoration($ids): Builder
    {
        return $this->newQuery()->whereKey($ids);
    }

    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
        static::saving(function (Model $model): void {
            $tenantId = app(TenantContext::class)->requireTenant()->id;
            if (($model->exists && (int) $model->getRawOriginal('tenant_id') !== $tenantId)
                || ($model->getAttribute('tenant_id') !== null && (int) $model->getAttribute('tenant_id') !== $tenantId)) {
                throw new AuthorizationException('The record does not belong to the active studio.');
            }
            $model->setAttribute('tenant_id', $tenantId);
        });
        static::deleting(function (Model $model): void {
            if ((int) $model->getRawOriginal('tenant_id') !== app(TenantContext::class)->requireTenant()->id) {
                throw new AuthorizationException('The record does not belong to the active studio.');
            }
        });
    }
}
