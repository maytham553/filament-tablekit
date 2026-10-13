<?php

namespace Elite\TableViews\Concerns;

use Elite\TableViews\Actions\QuickSaveTableViewAction;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Support\CurrentContext;
use Elite\TableViews\Support\TableViewsOptions;
use Elite\TableViews\Support\TableViewState;
use Filament\Actions\Action;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportAttributes\AttributeLevel;
use Livewire\Features\SupportQueryString\BaseUrl;

/**
 * Add to a ListRecords / ManageRelatedRecords page, relation manager or table widget.
 * Every member contains "TableView" to stay clear of Filament's own table concerns.
 */
trait HasTableViews
{
    private const TABLE_VIEW_URL_PROPERTIES = [
        'tableFilters', 'tableSearch', 'tableSort', 'tableGrouping', 'activeTab', 'isTableReordering',
    ];

    #[Locked]
    public int|string|null $activeTableViewId = null;

    #[Locked]
    public ?string $activeTableViewHash = null;

    protected bool $shouldMountHasTableViews = false;

    public function mountHasTableViews(): void
    {
        $this->shouldMountHasTableViews = true;
    }

    public function bootedHasTableViews(): void
    {
        if (! isset($this->table) || ! $this->shouldMountHasTableViews) {
            return;
        }

        $this->shouldMountHasTableViews = false;

        if (! TableViewsOptions::resolve()->persistActiveViewInSession() || $this->requestHasTableViewsQueryState()) {
            return;
        }

        $remembered = session()->get($this->getActiveTableViewSessionKey());

        if (blank($remembered)) {
            return;
        }

        $before = $this->snapshotTableViewUrlProperties();
        $persisted = $this->snapshotPersistedTableViewParts();

        if (! $this->restoreRememberedTableView($remembered)) {
            return;
        }

        $this->restorePersistedTableViewParts($persisted);
        $this->keepChangedTableViewUrlProperties($before);
    }

    /**
     * What the session already holds for this table when the page loads, before the remembered view is applied: column toggles and the sort.
     * The toggles count only when they differ from the table defaults, because Filament writes the defaults to the session on every first request.
     *
     * @return array{toggles: array<string, bool>|null, sort: string|null}
     */
    protected function snapshotPersistedTableViewParts(): array
    {
        $toggles = $this->captureTableViewToggles();

        return [
            'toggles' => $this->getTable()->persistsColumnsInSession() && $toggles !== $this->defaultTableViewToggles() ? $toggles : null,
            'sort' => $this->getTable()->persistsSortInSession() && filled($this->tableSort) ? $this->tableSort : null,
        ];
    }

    /**
     * A view applied while the page loads must not undo what the user changed after applying it and the session kept.
     *
     * @param  array{toggles: array<string, bool>|null, sort: string|null}  $persisted
     */
    protected function restorePersistedTableViewParts(array $persisted): void
    {
        if ($persisted['toggles'] !== null) {
            $this->applyTableViewToggles($persisted['toggles']);
        }

        if ($persisted['sort'] !== null) {
            $this->tableSort = $persisted['sort'];
            $this->updatedTableSort();
            $this->flushCachedTableRecords();
        }
    }

    /**
     * The browser drops a URL parameter whose value equals the value the property had after mount, and it never writes a null.
     * A view applied during mount changes that baseline, so the state it set (and every later edit that lands back on it, or on null)
     * would vanish from the URL and a reload would start from the table defaults. Marking those properties as always shown makes the
     * browser write them whatever their value. Properties the view did not touch keep the normal behavior: absent means the table default.
     *
     * @return array<string, string|false>
     */
    protected function snapshotTableViewUrlProperties(): array
    {
        $snapshot = [];

        foreach ($this->getTableViewUrlAttributes() as $name => $attribute) {
            $snapshot[$name] = json_encode($this->{$name});
        }

        return $snapshot;
    }

    /**
     * @param  array<string, string|false>  $before
     */
    protected function keepChangedTableViewUrlProperties(array $before): void
    {
        foreach ($this->getTableViewUrlAttributes() as $name => $attribute) {
            if (($before[$name] ?? null) !== json_encode($this->{$name})) {
                $attribute->keep = true;
            }
        }
    }

    /**
     * @return array<string, BaseUrl>
     */
    protected function getTableViewUrlAttributes(): array
    {
        $attributes = [];

        foreach ($this->getAttributes() as $attribute) {
            if ($attribute instanceof BaseUrl
                && $attribute->getLevel() === AttributeLevel::PROPERTY
                && in_array($attribute->getName(), self::TABLE_VIEW_URL_PROPERTIES, true)
                && property_exists($this, $attribute->getName())) {
                $attributes[$attribute->getName()] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * Only a component whose own table state is URL-bound (ListRecords, ManageRelatedRecords) can have table state in the URL.
     * The parameter names are read from the #[Url] attributes, because Filament 4 and 5 renamed them (filters, search, sort, grouping, tab, reordering).
     */
    protected function requestHasTableViewsQueryState(): bool
    {
        foreach ($this->getTableViewUrlAttributes() as $attribute) {
            if (request()->query->has($attribute->urlName())) {
                return true;
            }
        }

        return false;
    }

    public function applyTableView(mixed $viewId): void
    {
        $view = $this->findTableView($viewId);

        if ($view) {
            $this->activateTableView($view);
        }
    }

    protected function activateTableView(TableView $view, bool $remember = true): void
    {
        $this->applyTableViewState($view->state);

        $this->activeTableViewId = $view->getKey();
        $this->activeTableViewHash = $this->captureTableViewState()->hash();

        if ($remember) {
            $this->rememberActiveTableView((string) $view->getKey());
        }
    }

    public function applyDefaultTableView(): void
    {
        $this->resetTableViewParts();

        $this->activeTableViewId = null;
        $this->activeTableViewHash = null;

        $this->forgetActiveTableView();
    }

    protected function quickSaveTableViewAction(): Action
    {
        return QuickSaveTableViewAction::make(
            $this->getTableViewsResourceKey(),
            fn (array $data): TableView => $this->saveTableView($data),
        );
    }

    protected function getTableViewsResourceKey(): string
    {
        return static::class;
    }

    /**
     * Favorite views of the current user for this table, in their saved order.
     *
     * @return Collection<int, TableView>
     */
    protected function getTableViewsForBar(): Collection
    {
        return TableView::forCurrentContext($this->getTableViewsResourceKey())->favorites()->ordered()->get();
    }

    protected function isActiveTableViewModified(): bool
    {
        if ($this->activeTableViewId === null) {
            return false;
        }

        return $this->activeTableViewHash !== $this->captureTableViewState()->hash();
    }

    protected function captureTableViewState(): TableViewState
    {
        $sortColumn = $this->getTableSortColumn();
        $grouped = filled($this->tableGrouping);

        return TableViewState::fromArray([
            'v' => TableViewState::VERSION,
            'filters' => $this->tableFilters ?? [],
            'search' => (string) ($this->tableSearch ?? ''),
            'columnSearches' => $this->castTableColumnSearches(
                $this->removeBlankTableViewSearches($this->tableColumnSearches ?? []),
            ),
            'sort' => [
                'column' => filled($sortColumn) ? $sortColumn : null,
                'direction' => filled($sortColumn) ? $this->getTableSortDirection() : null,
            ],
            'toggledColumns' => $this->captureTableViewToggles(),
            'grouping' => [
                'column' => $grouped ? (string) str($this->tableGrouping)->before(':') : null,
                'direction' => $grouped ? $this->getTableGroupingDirection() : null,
            ],
            'tab' => $this->shouldTrackTableViewTab() ? $this->activeTab : null,
        ]);
    }

    /**
     * Back to the table's own defaults. This also puts the column order back, because Filament keeps order and visibility in one state.
     */
    protected function resetTableViewParts(): void
    {
        $table = $this->getTable();

        $this->isTableReordering = false;

        $this->getTableFiltersForm()->fill();
        $this->applyTableViewFilters();

        $this->resetTableSearch();
        $this->resetTableColumnSearches();

        $this->tableSort = null;
        $this->updatedTableSort();

        $this->resetTableColumnManager();

        $this->tableGrouping = $table->isDefaultGroupSelectable()
            ? $table->getDefaultGroup()->getId().':'.$table->getDefaultGroupDirection()
            : null;
        $this->updatedTableGrouping();

        if ($this->shouldTrackTableViewTab()) {
            $default = $this->getDefaultActiveTab();
            $this->activeTab = filled($default) ? (string) $default : null;
            $this->updatedActiveTab();
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    protected function applyTableViewState(TableViewState $state): void
    {
        $table = $this->getTable();

        $this->isTableReordering = false;

        $filters = Arr::only($state->filters, array_keys($table->getFilters(withHidden: true)));
        $this->getTableFiltersForm()->fill($filters);
        $this->applyTableViewFilters();

        $this->tableSearch = $state->search;
        $this->updatedTableSearch();

        $this->tableColumnSearches = $this->castTableColumnSearches($state->columnSearches);
        $this->fillReservedTableColumnSearchKeys();
        $this->updatedTableColumnSearches();

        $sortable = $state->sortColumn !== null ? $table->getSortableVisibleColumn($state->sortColumn) : null;
        $this->tableSort = $sortable ? $sortable->getName().':'.($state->sortDirection === 'desc' ? 'desc' : 'asc') : null;
        $this->updatedTableSort();

        $this->applyTableViewToggles($state->toggledColumns);

        $group = $state->groupingColumn !== null ? $table->getGroup($state->groupingColumn) : null;
        $this->tableGrouping = $group
            ? $group->getId().(in_array($state->groupingDirection, ['asc', 'desc'], true) ? ':'.$state->groupingDirection : '')
            : null;
        $this->updatedTableGrouping();

        if ($this->shouldTrackTableViewTab() && $state->tab !== null && array_key_exists($state->tab, $this->getCachedTabs())) {
            $this->activeTab = $state->tab;
            $this->updatedActiveTab();
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    protected function applyTableViewFilters(): void
    {
        $this->getTable()->hasDeferredFilters() ? $this->applyTableFilters() : $this->handleTableFilterUpdates();
    }

    /**
     * Saved values win; toggleable columns missing from the saved state keep their default, so a column added later is not hidden.
     * Names that are no longer toggleable columns are dropped. The current column order is kept.
     *
     * @param  array<string, mixed>  $saved  column name => shown (a nested map from Filament 3 is flattened)
     */
    protected function applyTableViewToggles(array $saved): void
    {
        if ($this->getTable()->hasColumnsLayout()) {
            return;
        }

        $saved = Arr::dot($saved);
        $defaults = $this->defaultTableViewToggles();

        $this->applyTableColumnManager($this->mapTableViewColumnItems(
            $this->tableColumns,
            function (array $item) use ($saved, $defaults): array {
                if ($item['isToggleable'] && array_key_exists($item['name'], $defaults)) {
                    $item['isToggled'] = array_key_exists($item['name'], $saved) ? (bool) $saved[$item['name']] : $defaults[$item['name']];
                }

                return $item;
            },
        ));
    }

    /**
     * The shown/hidden state of every toggleable column right now.
     *
     * @return array<string, bool>
     */
    protected function captureTableViewToggles(): array
    {
        return $this->flattenTableViewToggles($this->tableColumns);
    }

    /**
     * @return array<string, bool>
     */
    protected function defaultTableViewToggles(): array
    {
        return $this->flattenTableViewToggles($this->getDefaultTableColumnState());
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, bool>
     */
    protected function flattenTableViewToggles(array $items): array
    {
        $toggles = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === self::TABLE_COLUMN_MANAGER_GROUP_TYPE) {
                $toggles = [...$toggles, ...$this->flattenTableViewToggles($item['columns'] ?? [])];

                continue;
            }

            if (($item['isToggleable'] ?? false) && is_string($item['name'] ?? null)) {
                $toggles[$item['name']] = (bool) ($item['isToggled'] ?? true);
            }
        }

        return $toggles;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function mapTableViewColumnItems(array $items, \Closure $map): array
    {
        return array_map(function (array $item) use ($map): array {
            if (($item['type'] ?? null) === self::TABLE_COLUMN_MANAGER_GROUP_TYPE) {
                $item['columns'] = $this->mapTableViewColumnItems($item['columns'] ?? [], $map);

                return $item;
            }

            return $map($item);
        }, $items);
    }

    protected function saveTableView(array $data): TableView
    {
        $userId = CurrentContext::userId();

        abort_if($userId === null, 403);

        $state = $this->captureTableViewState();
        $options = TableViewsOptions::resolve();

        if (strlen($state->toJson()) > $options->maxStateBytes()) {
            throw ValidationException::withMessages([
                'mountedActions.'.array_key_last($this->mountedActions).'.data.name' => __('table-views::validation.state_too_large'),
            ]);
        }

        $resourceKey = $this->getTableViewsResourceKey();

        $view = TableView::query()->create([
            'user_id' => $userId,
            'name' => trim($data['name']),
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'resource' => $resourceKey,
            'state' => $state,
            'is_favorite' => (bool) ($data['is_favorite'] ?? true),
            'sort' => TableView::nextSort($resourceKey),
            'tenant_type' => CurrentContext::tenantType(),
            'tenant_id' => CurrentContext::tenantId(),
        ]);

        $this->activeTableViewId = $view->getKey();
        $this->activeTableViewHash = $state->hash();

        $this->rememberActiveTableView((string) $view->getKey());

        return $view;
    }

    /**
     * The id comes from the browser: only a plain positive bigint-range integer may reach the query.
     */
    protected function findTableView(mixed $viewId): ?TableView
    {
        if (is_string($viewId) && strlen($viewId) <= 18 && ctype_digit($viewId)) {
            $viewId = (int) $viewId;
        }

        if (! is_int($viewId) || $viewId < 1) {
            return null;
        }

        return TableView::forCurrentContext($this->getTableViewsResourceKey())->find($viewId);
    }

    /**
     * @return bool true when the remembered view was applied; a missing or deleted view is forgotten
     */
    protected function restoreRememberedTableView(mixed $remembered): bool
    {
        if ($view = $this->findTableView($remembered)) {
            $this->activateTableView($view, remember: false);

            return true;
        }

        $this->forgetActiveTableView();

        return false;
    }

    protected function rememberActiveTableView(string $value): void
    {
        if (TableViewsOptions::resolve()->persistActiveViewInSession()) {
            session()->put($this->getActiveTableViewSessionKey(), (int) $value);
        }
    }

    protected function getActiveTableViewSessionKey(): string
    {
        return 'table-views.'.md5($this->getTableViewsResourceKey().'|'.CurrentContext::tenantType().'|'.CurrentContext::tenantId()).'.active';
    }

    protected function forgetActiveTableView(): void
    {
        session()->forget($this->getActiveTableViewSessionKey());
    }

    protected function shouldTrackTableViewTab(): bool
    {
        return TableViewsOptions::resolve()->captureTab()
            && property_exists($this, 'activeTab')
            && method_exists($this, 'getCachedTabs');
    }

    /**
     * @param  array<string, mixed>  $searches
     * @return array<string, mixed>
     */
    protected function removeBlankTableViewSearches(array $searches): array
    {
        $result = [];

        foreach ($searches as $key => $value) {
            if (is_array($value)) {
                $value = $this->removeBlankTableViewSearches($value);

                if ($value !== []) {
                    $result[$key] = $value;
                }

                continue;
            }

            if (filled($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
