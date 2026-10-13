<?php

namespace TableKit\Concerns;

use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use TableKit\Support\ReorderedKeys;
use TableKit\Support\TableKitOptions;

/**
 * Sort by several columns. Filament keeps one sort (the `tableSort` string `column:direction`); that stays the primary sort and the others ride on it:
 * every sortable column gets a wrapper that, when it is the primary, also applies the following sorts through the original column.
 */
trait HasMultiSortTableViews
{
    /**
     * Two or more sorts, primary first. Only trusted while its first entry is Filament's current sort (see effectiveTableViewSorts).
     *
     * @var array<int, array{column: string, direction: string}>
     */
    #[Locked]
    public array $tableViewSorts = [];

    /** URL mirror of the extra sorts (`column:direction,...`). Written on the server only, read back and validated on a fresh load. */
    #[Locked]
    public mixed $tableViewSortsUrl = null;

    /** @var array<string, Column> the columns as the developer defined them, before the wrapper */
    protected array $tableViewOriginalSortColumns = [];

    /** @var array<string, Column>|null */
    protected ?array $cachedTableViewSortableColumns = null;

    /** The `Table` object the wrapper was installed on: a second install on the same object would wrap the wrapper. */
    protected ?Table $tableViewSortsTable = null;

    /** Return false from a component to turn multi-sort off for its table. */
    public static function multiSortTableViews(): bool
    {
        return true;
    }

    protected function isTableViewMultiSortEnabled(): bool
    {
        return static::multiSortTableViews()
            && TableKitOptions::resolve()->multiSortEnabled()
            && count($this->getTableViewSortableColumns()) >= 2;
    }

    /**
     * @return array<string, Column>
     */
    protected function getTableViewSortableColumns(): array
    {
        $this->installTableViewSortWrapper();

        return $this->cachedTableViewSortableColumns ??= array_filter(
            $this->getTable()->getColumns(),
            fn (Column $column): bool => $column->isSortable() && ! $column->isHidden(),
        );
    }

    protected function bootTableViewSorts(): void
    {
        $this->installTableViewSortWrapper();

        if ($this->isTableViewMultiSortEnabled()) {
            $this->restoreTableViewSortsFromSession();
        }
    }

    /**
     * Idempotent per `Table` object: a rebuilt table (Filament's `resetTable()`) gets its own wrapper, the same table never gets a second one.
     */
    protected function installTableViewSortWrapper(): void
    {
        if (! isset($this->table) || $this->tableViewSortsTable === $this->table) {
            return;
        }

        $this->tableViewSortsTable = $this->table;
        $this->tableViewOriginalSortColumns = [];
        $this->cachedTableViewSortableColumns = null;

        if (! $this->isTableViewMultiSortEnabled()) {
            return;
        }

        foreach ($this->getTableViewSortableColumns() as $name => $column) {
            $name = (string) $name;
            $original = clone $column;
            $this->tableViewOriginalSortColumns[$name] = $original;

            $column->sortable(true, function (Builder $query, string $direction) use ($name, $original): void {
                $original->applySort($query, $direction);

                foreach ($this->getTableViewExtraSorts($name, $direction) as $sort) {
                    $this->tableViewOriginalSortColumns[$sort['column']]->applySort($query, $sort['direction']);
                }
            });

            $column->extraHeaderAttributes(fn (): array => $this->getTableViewSortHeaderAttributes($name), merge: true);
        }
    }

    /**
     * Like Filament's own sort, the extra sorts survive in the session with `persistSortInSession()`. Accepted only when they start with the sort Filament restored.
     */
    protected function restoreTableViewSortsFromSession(): void
    {
        if (! $this->shouldMountHasTableKit || ! $this->getTable()->persistsSortInSession() || ! filled($this->getTableSortColumn())) {
            return;
        }

        $saved = session()->get($this->getTableViewSessionKey('sorts'));

        if (! is_array($saved)) {
            return;
        }

        $sorts = $this->normalizeTableViewSorts($saved);
        $primary = $this->getTableViewPrimarySort();

        if (count($sorts) >= 2 && $primary !== null && $this->isSameTableViewSort($sorts[0], $primary)) {
            $this->tableViewSorts = $sorts;
        }
    }

    /**
     * @return array{column: string, direction: string}|null
     */
    protected function getTableViewPrimarySort(): ?array
    {
        $column = $this->getTableSortColumn();

        return filled($column) ? ['column' => $column, 'direction' => $this->getTableSortDirection() === 'desc' ? 'desc' : 'asc'] : null;
    }

    protected function persistTableViewSorts(): void
    {
        if (! $this->isTableViewMultiSortEnabled() || ! $this->getTable()->persistsSortInSession()) {
            return;
        }

        $key = $this->getTableViewSessionKey('sorts');

        count($this->tableViewSorts) >= 2 ? session()->put($key, $this->tableViewSorts) : session()->forget($key);
    }

    /**
     * The sorts the table applies when nothing was chosen: its string `defaultSort()` column, when that column is sortable and visible.
     *
     * @return array<int, array{column: string, direction: string}>
     */
    protected function getTableViewDefaultSorts(): array
    {
        $table = $this->getTable();
        $column = $table->getDefaultSortColumn();

        if (! is_string($column) || ! isset($this->getTableViewSortableColumns()[$column])) {
            return [];
        }

        return [['column' => $column, 'direction' => $table->getDefaultSortDirection() === 'desc' ? 'desc' : 'asc']];
    }

    /** Sorts can be changed only while records are not being reordered: Filament ignores them then. */
    protected function canChangeTableViewSorts(): bool
    {
        return $this->isTableViewMultiSortEnabled() && ! $this->isTableReordering();
    }

    /**
     * The sorts that follow the primary, only when `$column` with `$direction` is the primary of the stored list.
     *
     * @return array<int, array{column: string, direction: string}>
     */
    protected function getTableViewExtraSorts(string $column, string $direction): array
    {
        $list = $this->tableViewSorts;

        if ($this->getTableSortColumn() !== $column || count($list) < 2 || ($list[0]['column'] ?? null) !== $column || ($list[0]['direction'] ?? null) !== ($direction === 'desc' ? 'desc' : 'asc')) {
            return [];
        }

        return array_values(array_filter(
            array_slice($list, 1, TableKitOptions::resolve()->maxSorts() - 1),
            fn (array $sort): bool => isset($this->tableViewOriginalSortColumns[$sort['column']]),
        ));
    }

    /**
     * What is sorted right now, primary first. The stored list only counts while it still starts with Filament's current sort:
     * a header click, the mobile sort select or an edited URL change that sort and so end the multi-sort.
     *
     * @return array<int, array{column: string, direction: string}>
     */
    protected function effectiveTableViewSorts(): array
    {
        $primary = $this->getTableViewPrimarySort();

        if ($primary === null || ! isset($this->getTableViewSortableColumns()[$primary['column']])) {
            return [];
        }

        if (count($this->tableViewSorts) >= 2 && $this->isSameTableViewSort($this->tableViewSorts[0] ?? null, $primary) && $this->isTableViewMultiSortEnabled()) {
            $columns = $this->getTableViewSortableColumns();

            return array_values(array_filter($this->tableViewSorts, fn (array $sort): bool => isset($columns[$sort['column']])));
        }

        return [$primary];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getTableViewSortHeaderAttributes(string $column): array
    {
        $sorts = $this->effectiveTableViewSorts();

        if (count($sorts) < 2) {
            return [];
        }

        foreach ($sorts as $index => $sort) {
            if ($sort['column'] === $column) {
                return ['data-sort-priority' => $index + 1];
            }
        }

        return [];
    }

    public function addTableViewSort(mixed $column): void
    {
        if (! $this->canChangeTableViewSorts() || ! is_string($column) || ! isset($this->getTableViewSortableColumns()[$column])) {
            return;
        }

        $sorts = $this->effectiveTableViewSorts();
        $seeded = false;

        if ($sorts === []) {
            $sorts = $this->getTableViewDefaultSorts();
            $seeded = $sorts !== [];
        }

        if (in_array($column, array_column($sorts, 'column'), true)) {
            if ($seeded) {
                $this->commitTableViewSorts($sorts);
            }

            return;
        }

        if (count($sorts) >= TableKitOptions::resolve()->maxSorts()) {
            return;
        }

        $sorts[] = ['column' => $column, 'direction' => 'asc'];

        $this->commitTableViewSorts($sorts);
    }

    public function setTableViewSortDirection(mixed $column, mixed $direction): void
    {
        if (! $this->canChangeTableViewSorts() || ! is_string($column) || ! in_array($direction, ['asc', 'desc'], true)) {
            return;
        }

        $sorts = $this->effectiveTableViewSorts();
        $index = array_search($column, array_column($sorts, 'column'), true);

        if ($index === false) {
            return;
        }

        $sorts[$index]['direction'] = $direction;

        $this->commitTableViewSorts($sorts);
    }

    public function removeTableViewSort(mixed $column): void
    {
        if (! $this->canChangeTableViewSorts() || ! is_string($column)) {
            return;
        }

        $sorts = $this->effectiveTableViewSorts();
        $index = array_search($column, array_column($sorts, 'column'), true);

        if ($index === false) {
            return;
        }

        unset($sorts[$index]);

        $this->commitTableViewSorts(array_values($sorts));
    }

    /**
     * Receives the whole new priority order after a drag or a keyboard move: exactly the columns that are sorted right now, each once.
     */
    public function reorderTableViewSorts(mixed $columns): void
    {
        if (! $this->canChangeTableViewSorts()) {
            return;
        }

        $sorts = $this->effectiveTableViewSorts();
        $columns = ReorderedKeys::validate($columns, array_column($sorts, 'column'));

        if ($columns === null || count($sorts) < 2) {
            return;
        }

        $byColumn = array_column($sorts, null, 'column');
        $reordered = array_map(fn (string $column): array => $byColumn[$column], $columns);

        if ($reordered !== $sorts) {
            $this->commitTableViewSorts($reordered);
        }
    }

    public function reorderTableViewSort(mixed $column, mixed $direction): void
    {
        if (! is_string($column) || ! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        $columns = array_column($this->effectiveTableViewSorts(), 'column');
        $index = array_search($column, $columns, true);
        $target = $index === false ? null : $index + ($direction === 'up' ? -1 : 1);

        if ($target === null || ! isset($columns[$target])) {
            return;
        }

        [$columns[$index], $columns[$target]] = [$columns[$target], $columns[$index]];

        $this->reorderTableViewSorts($columns);
    }

    public function clearTableViewSorts(): void
    {
        if ($this->canChangeTableViewSorts()) {
            $this->commitTableViewSorts([]);
        }
    }

    /**
     * @param  array<int, array{column: string, direction: string}>  $sorts
     */
    protected function commitTableViewSorts(array $sorts): void
    {
        $sorts = array_slice(array_values($sorts), 0, TableKitOptions::resolve()->maxSorts());

        $this->setTableViewSorts($sorts);

        $this->updatedTableSort();
        $this->flushCachedTableRecords();
    }

    /**
     * @param  array<string, mixed>|null  $sort
     * @param  array{column: string, direction: string}  $other
     */
    protected function isSameTableViewSort(?array $sort, array $other): bool
    {
        return ($sort['column'] ?? null) === $other['column'] && ($sort['direction'] ?? null) === $other['direction'];
    }

    /**
     * Only sortable visible columns, each once, directions asc|desc, at most the configured count (one when multi-sort is off).
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array{column: string, direction: string}>
     */
    protected function normalizeTableViewSorts(array $entries): array
    {
        $columns = $this->getTableViewSortableColumns();
        $valid = [];

        foreach ($entries as $entry) {
            $column = $entry['column'] ?? null;

            if (is_string($column) && isset($columns[$column]) && ! isset($valid[$column])) {
                $valid[$column] = ['column' => $column, 'direction' => ($entry['direction'] ?? null) === 'desc' ? 'desc' : 'asc'];
            }
        }

        $limit = $this->isTableViewMultiSortEnabled() ? TableKitOptions::resolve()->maxSorts() : 1;

        return array_slice(array_values($valid), 0, $limit);
    }

    /**
     * Sets the sorts a view describes: the primary goes to Filament's own properties.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    protected function setTableViewSorts(array $entries): void
    {
        $sorts = $this->normalizeTableViewSorts($entries);

        $this->tableViewSorts = count($sorts) >= 2 ? $sorts : [];
        $this->tableSort = $sorts === [] ? null : $sorts[0]['column'].':'.$sorts[0]['direction'];
    }

    protected function serializeTableViewSortsForUrl(): ?string
    {
        $sorts = $this->effectiveTableViewSorts();

        if (count($sorts) < 2) {
            return null;
        }

        return implode(',', array_map(fn (array $sort): string => rawurlencode($sort['column']).':'.$sort['direction'], $sorts));
    }

    /**
     * The extra sorts of a fresh page load. Accepted only when they start with the sort the URL already carries; anything else is ignored.
     */
    protected function restoreTableViewSortsFromUrl(mixed $value): void
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2000 || ! $this->isTableViewMultiSortEnabled()) {
            return;
        }

        $entries = [];

        foreach (explode(',', $value) as $part) {
            [$column, $direction] = array_pad(explode(':', $part, 2), 2, 'asc');

            $entries[] = ['column' => rawurldecode($column), 'direction' => $direction];
        }

        $sorts = $this->normalizeTableViewSorts($entries);
        $primary = $this->effectiveTableViewSorts()[0] ?? null;

        if (count($sorts) >= 2 && $primary !== null && $this->isSameTableViewSort($sorts[0], $primary)) {
            $this->tableViewSorts = $sorts;
        }
    }
}
