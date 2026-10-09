<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @template TModel of Model
 *
 * @extends TenantBuilder<TModel>
 */
class CreditEntryBuilder extends TenantBuilder
{
    /** @param array<string, mixed> $values */
    public function update(array $values): never
    {
        throw new LogicException('Credit entries are append-only.');
    }

    public function delete(): never
    {
        throw new LogicException('Credit entries are append-only.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Credit entries are append-only.');
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, string>|string  $uniqueBy
     * @param  array<array-key, string>|null  $update
     */
    public function upsert(array $values, mixed $uniqueBy, mixed $update = null): never
    {
        throw new LogicException('Credit entries are append-only.');
    }

    /** @param array<string, mixed> $extra */
    public function increment(mixed $column, mixed $amount = 1, array $extra = []): never
    {
        throw new LogicException('Credit entries are append-only.');
    }

    /** @param array<string, mixed> $extra */
    public function decrement(mixed $column, mixed $amount = 1, array $extra = []): never
    {
        throw new LogicException('Credit entries are append-only.');
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function incrementEach(array $columns, array $extra = []): never
    {
        throw new LogicException('Credit entries are append-only.');
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function decrementEach(array $columns, array $extra = []): never
    {
        throw new LogicException('Credit entries are append-only.');
    }

    /** @param array<array-key, string>|string|null $column */
    public function touch(mixed $column = null): never
    {
        throw new LogicException('Credit entries are append-only.');
    }
}
