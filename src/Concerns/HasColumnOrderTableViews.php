<?php

namespace TableKit\Concerns;

use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Livewire\Attributes\Locked;
use TableKit\Support\ReorderedKeys;
use TableKit\Support\TableKitOptions;

/**
 * Column order. Filament rebuilds the table on every request and renders headers, cells and summaries from `Table::getColumns()`,
 * so the order is applied once per request on the freshly built table: the same Column objects, pushed again in the saved order.
 */
trait HasColumnOrderTableViews
{
    /** Column names in display order. Empty = the order defined in code. Written on the server only. */
    #[Locked]
    public array $tableViewColumnOrder = [];

    /** @var array<string, Column>|null the columns as the developer defined them, in code order */
    protected ?array $tableViewCodeColumns = null;

    /** The `Table` object the code columns and the order were taken from and applied to. */
    protected ?Table $tableViewColumnOrderTable = null;

    /** Return false from a component to turn column reordering off for its table. */
    public static function columnOrderTableViews(): bool
    {
        return true;
    }

    protected function isTableViewColumnOrderEnabled(): bool
    {
        return static::columnOrderTableViews()
            && TableKitOptions::resolve()->columnReorderingEnabled()
            && $this->supportsTableViewColumnOrder()
            && count($this->getTableViewOrderableColumnNames()) >= 2;
    }

    /**
     * A flat list of columns only: column groups, layout components and a collapsible layout have no clean meaning for "move up".
     */
    protected function supportsTableViewColumnOrder(): bool
    {
        $table = $this->getTable();

        if ($table->getCollapsibleColumnsLayout() !== null) {
            return false;
        }

        foreach ($table->getColumnsLayout() as $component) {
            if (! $component instanceof Column) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string> the columns that are not hidden by the developer, in the current order
     */
    protected function getTableViewOrderableColumnNames(): array
    {
        $names = [];

        foreach ($this->getTable()->getColumns() as $name => $column) {
            if (! $column->isHidden()) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * The code columns belong to one `Table` object. Filament's `resetTable()` builds a new one (new Column objects, code order, and a fresh
     * column toggle form), so the order is applied again on it before it renders.
     */
    protected function syncTableViewCodeColumns(): bool
    {
        $table = $this->getTable();

        if ($this->tableViewColumnOrderTable === $table) {
            return false;
        }

        $this->tableViewColumnOrderTable = $table;
        $this->tableViewCodeColumns = $table->getColumns();

        return true;
    }

    protected function restoreTableViewColumnOrder(): void
    {
        if (! $this->syncTableViewCodeColumns() || ! $this->isTableViewColumnOrderEnabled()) {
            return;
        }

        $this->applyTableViewColumnOrder();
        $this->cacheForm('toggleTableColumnForm', fn () => $this->makeTableViewColumnToggleForm());
    }

    protected function bootTableViewColumnOrder(): void
    {
        $this->syncTableViewCodeColumns();

        if (! $this->isTableViewColumnOrderEnabled()) {
            return;
        }

        if ($this->shouldMountHasTableKit) {
            $saved = session()->get($this->getTableViewSessionKey('column_order'));

            $this->tableViewColumnOrder = is_array($saved) ? $this->normalizeTableViewColumnOrder($saved) : [];
        }

        $this->applyTableViewColumnOrder();
        $this->cacheForm('toggleTableColumnForm', fn () => $this->makeTableViewColumnToggleForm());
    }

    /**
     * The code order, with the saved names first (in saved order) and every column the saved list does not know appended.
     * Names that no longer exist drop out.
     *
     * @param  array<int, mixed>  $saved
     * @return array<int, string>
     */
    protected function resolveTableViewColumnOrder(array $saved): array
    {
        $code = array_map('strval', array_keys($this->tableViewCodeColumns ?? []));
        $known = array_values(array_intersect(array_unique(array_map('strval', array_filter($saved, 'is_scalar'))), $code));

        return [...$known, ...array_values(array_diff($code, $known))];
    }

    /**
     * What gets stored: the full order, or an empty list when it is the code order.
     *
     * @param  array<int, mixed>  $names
     * @return array<int, string>
     */
    protected function normalizeTableViewColumnOrder(array $names): array
    {
        $order = $this->resolveTableViewColumnOrder($names);

        return $order === array_map('strval', array_keys($this->tableViewCodeColumns ?? [])) ? [] : $order;
    }

    protected function applyTableViewColumnOrder(): void
    {
        if (! $this->isTableViewColumnOrderEnabled()) {
            return;
        }

        $table = $this->getTable();
        $order = $this->resolveTableViewColumnOrder($this->tableViewColumnOrder);

        if ($order === array_map('strval', array_keys($table->getColumns()))) {
            return;
        }

        $table->columns(array_map(fn (string $name): Column => $this->tableViewCodeColumns[$name], $order));
    }

    /**
     * @param  array<int, mixed>  $names
     */
    protected function setTableViewColumnOrder(array $names): void
    {
        $this->restoreTableViewColumnOrder();

        $this->tableViewColumnOrder = $this->normalizeTableViewColumnOrder($names);

        if ($this->tableViewColumnOrder === []) {
            session()->forget($this->getTableViewSessionKey('column_order'));
        } else {
            session()->put($this->getTableViewSessionKey('column_order'), $this->tableViewColumnOrder);
        }

        $this->applyTableViewColumnOrder();
    }

    /**
     * Receives the whole new order of the columns shown in the panel after a drag or a keyboard move: exactly those columns, each once.
     */
    public function reorderTableViewColumns(mixed $names): void
    {
        $this->restoreTableViewColumnOrder();

        if (! $this->isTableViewColumnOrderEnabled()) {
            return;
        }

        $visible = $this->getTableViewOrderableColumnNames();
        $names = ReorderedKeys::validate($names, $visible);

        if ($names === null) {
            return;
        }

        $this->setTableViewColumnOrder(ReorderedKeys::mergeInto(
            $this->resolveTableViewColumnOrder($this->tableViewColumnOrder),
            $visible,
            $names,
        ));
    }

    public function reorderTableViewColumn(mixed $name, mixed $direction): void
    {
        $this->restoreTableViewColumnOrder();

        if (! $this->isTableViewColumnOrderEnabled() || ! is_string($name) || ! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        $visible = $this->getTableViewOrderableColumnNames();
        $position = array_search($name, $visible, true);
        $neighbour = $position === false ? null : $position + ($direction === 'up' ? -1 : 1);

        if ($neighbour === null || ! isset($visible[$neighbour])) {
            return;
        }

        [$visible[$position], $visible[$neighbour]] = [$visible[$neighbour], $visible[$position]];

        $this->reorderTableViewColumns($visible);
    }

    public function resetTableViewColumnOrder(): void
    {
        if ($this->isTableViewColumnOrderEnabled()) {
            $this->setTableViewColumnOrder([]);
        }
    }

    /**
     * Replaces the content of Filament's column toggle dropdown: the same state (`toggledTableColumns`), plus the order controls.
     */
    protected function makeTableViewColumnToggleForm(): Form
    {
        return $this->makeForm()
            ->schema([View::make('tablekit::columns-list')])
            ->statePath('toggledTableColumns');
    }
}
