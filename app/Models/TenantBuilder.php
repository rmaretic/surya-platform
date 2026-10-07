<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @template TModel of Model
 * @extends Builder<TModel>
 */
class TenantBuilder extends Builder
{
    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        foreach (array_keys($values) as $column) {
            if (last(explode('.', $column)) === 'tenant_id') {
                throw new LogicException('Tenant ownership cannot be changed through a bulk update.');
            }
        }

        return parent::update($values);
    }
}
