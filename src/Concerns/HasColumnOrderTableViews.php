<?php

namespace TableKit\Concerns;

use Illuminate\Support\Arr;
use TableKit\Support\TableKitOptions;

/**
 * Column order in views and presets. Filament 4's column manager owns the order and its reordering UI (`reorderableColumns()`); this only saves what it
 * holds and puts it back through `applyTableColumnManager()`, together with the visibility.
 */
trait HasColumnOrderTableViews
{
    /** Return false from a component to keep the column order out of its views and presets. */
    public static function columnOrderTableViews(): bool
    {
        return true;
    }

    protected function isTableViewColumnOrderEnabled(): bool
    {
        $table = $this->getTable();

        return static::columnOrderTableViews()
            && TableKitOptions::resolve()->columnReorderingEnabled()
            && ! $table->hasColumnsLayout()
            && $table->hasReorderableColumns();
    }

    /**
     * Column names in the order the column manager shows them, column groups expanded in place. Empty when the order is the one defined in code.
     *
     * @return array<int, string>
     */
    protected function captureTableViewColumnOrder(): array
    {
        if (! $this->isTableViewColumnOrderEnabled()) {
            return [];
        }

        $order = $this->flattenTableViewColumnNames($this->tableColumns);

        return $order === $this->flattenTableViewColumnNames($this->getDefaultTableColumnState()) ? [] : $order;
    }

    /**
     * @return array<int, string>
     */
    protected function getTableViewCodeColumnOrder(): array
    {
        return $this->flattenTableViewColumnNames($this->getDefaultTableColumnState());
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    protected function flattenTableViewColumnNames(array $items): array
    {
        $names = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === self::TABLE_COLUMN_MANAGER_GROUP_TYPE) {
                array_push($names, ...$this->flattenTableViewColumnNames($item['columns'] ?? []));
            } elseif (is_string($item['name'] ?? null)) {
                $names[] = $item['name'];
            }
        }

        return $names;
    }

    /**
     * Visibility and order in one write to the column manager. Null leaves that part as it is; an empty order is "no saved order" and keeps the user's.
     *
     * @param  array<string, mixed>|null  $toggles  column name => shown (a nested map from Filament 3 is flattened)
     * @param  array<int, mixed>|null  $order
     */
    protected function applyTableViewColumns(?array $toggles, ?array $order = null): void
    {
        if ($this->getTable()->hasColumnsLayout()) {
            return;
        }

        $items = $this->tableColumns;
        $reordered = $order !== null && $order !== [] && $this->isTableViewColumnOrderEnabled();

        if ($reordered) {
            $items = $this->orderTableViewColumnItems($items, $order);
        }

        if ($toggles !== null) {
            $saved = Arr::dot($toggles);
            $defaults = $this->defaultTableViewToggles();

            $items = $this->mapTableViewColumnItems($items, function (array $item) use ($saved, $defaults): array {
                if ($item['isToggleable'] && array_key_exists($item['name'], $defaults)) {
                    $item['isToggled'] = array_key_exists($item['name'], $saved) ? (bool) $saved[$item['name']] : $defaults[$item['name']];
                }

                return $item;
            });
        }

        $this->applyTableColumnManager($items);

        // Filament re-pushes the columns into the table only while the order differs from the code order: put them back too when the asked order is the code order.
        if ($reordered) {
            $this->updateTableColumns();
        }
    }

    /**
     * The listed columns first, in the listed order; the others after them in code order (a column added after the order was saved lands at the end).
     * Names that no longer exist are skipped. A column group moves as one block and sorts its own columns the same way: columns never change group.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    protected function orderTableViewColumnItems(array $items, array $order): array
    {
        $rank = array_flip(array_values(array_unique(array_filter($order, 'is_string'))));
        $code = array_flip($this->getTableViewCodeColumnOrder());

        $score = fn (string $name): int => $rank[$name] ?? (1_000_000 + ($code[$name] ?? 0));

        $sort = function (array $entries, callable $scoreOf): array {
            usort($entries, fn (array $a, array $b): int => $scoreOf($a) <=> $scoreOf($b));

            return $entries;
        };

        $items = array_map(function (array $item) use ($sort, $score): array {
            if (($item['type'] ?? null) === self::TABLE_COLUMN_MANAGER_GROUP_TYPE) {
                $item['columns'] = $sort($item['columns'] ?? [], fn (array $column): int => $score((string) $column['name']));
            }

            return $item;
        }, $items);

        return array_values($sort($items, function (array $item) use ($score): int {
            if (($item['type'] ?? null) === self::TABLE_COLUMN_MANAGER_GROUP_TYPE) {
                return min([PHP_INT_MAX, ...array_map(fn (array $column): int => $score((string) $column['name']), $item['columns'] ?? [])]);
            }

            return $score((string) $item['name']);
        }));
    }

    protected function resetTableViewColumnOrderToCode(): void
    {
        if ($this->isTableViewColumnOrderEnabled()) {
            $this->applyTableViewColumns(null, $this->getTableViewCodeColumnOrder());
        }
    }
}
