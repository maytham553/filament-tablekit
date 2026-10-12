<?php

namespace Elite\TableViews\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<TableViewState, TableViewState|array<string, mixed>>
 */
class TableViewStateCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): TableViewState
    {
        if (blank($value)) {
            return new TableViewState;
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return TableViewState::fromArray(is_array($decoded) ? $decoded : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (is_array($value)) {
            $value = TableViewState::fromArray($value);
        }

        if (! $value instanceof TableViewState) {
            throw new InvalidArgumentException('The table view state must be a TableViewState or an array.');
        }

        return $value->toJson();
    }
}
