<?php

namespace Elite\TableViews\Concerns;

use Closure;
use Elite\TableViews\Actions\DeleteTableViewAction;
use Elite\TableViews\Actions\EditTableViewAction;
use Elite\TableViews\Actions\ManageTableViewsAction;
use Elite\TableViews\Actions\QuickSaveTableViewAction;
use Elite\TableViews\Models\TablePreference;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Support\CurrentContext;
use Elite\TableViews\Support\TableViewCatalog;
use Elite\TableViews\Support\TableViewPreferences;
use Elite\TableViews\Support\TableViewsOptions;
use Elite\TableViews\Support\TableViewState;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportAttributes\AttributeLevel;
use Livewire\Features\SupportQueryString\BaseUrl;

/**
 * Add to a ListRecords / ManageRelatedRecords page, relation manager or table widget.
 * Every member contains "TableView" or "TablePreset" to stay clear of Filament's own table concerns.
 */
trait HasTableViews
{
    use HasColumnOrderTableViews;
    use HasMultiSortTableViews;
    use HasQuickFilterTableViews;

    private const TABLE_VIEW_URL_PROPERTIES = [
        'tableFilters', 'tableSearch', 'tableSort', 'tableGrouping', 'activeTab', 'isTableReordering', 'tableViewUrl', 'tableViewSortsUrl',
    ];

    #[Locked]
    public int|string|null $activeTableViewId = null;

    #[Locked]
    public ?string $activeTableViewHash = null;

    /** The preset whose hidden query is applied. Server state only: the browser can never set it. */
    #[Locked]
    public ?string $activeTablePresetKey = null;

    /** URL mirror of the active view (`preset:<key>` / `user:<id>`). Written on the server only, read back and validated on a fresh load. */
    #[Locked]
    public mixed $tableViewUrl = null;

    #[Locked]
    public bool $tableViewsManagerLoaded = false;

    protected bool $shouldMountHasTableViews = false;

    /** @var array<string, PresetTableView>|null */
    protected ?array $cachedTablePresets = null;

    protected ?TableViewCatalog $cachedTableViewCatalog = null;

    /**
     * Livewire lets the browser call any public method except a few lifecycle names, so the trait hooks, which must be public, are refused here.
     */
    public function callHasTableViews(string $methodName, array $params, Closure $returnEarly): void
    {
        if (in_array($methodName, ['mountHasTableViews', 'bootedHasTableViews', 'renderingHasTableViews', 'callHasTableViews'], true)) {
            $returnEarly();
        }
    }

    /** The table can be rebuilt in the middle of a request (`resetTable()`), so what this plugin changes on it is put back before it renders. */
    public function renderingHasTableViews(): void
    {
        if (! isset($this->table)) {
            return;
        }

        $this->installTableViewSortWrapper();

        if ($this->isTableViewQuickFiltersEnabled()) {
            $this->getTable()->hiddenFilterIndicators();
        }
    }

    public function mountHasTableViews(): void
    {
        $this->shouldMountHasTableViews = true;
    }

    public function bootedHasTableViews(): void
    {
        if (! isset($this->table)) {
            return;
        }

        $this->bootTableViewSorts();
        $this->bootTableViewQuickFilters();

        if (! $this->shouldMountHasTableViews) {
            return;
        }

        $this->shouldMountHasTableViews = false;

        $requested = $this->tableViewUrl;
        $this->tableViewUrl = null;

        $this->restoreTableViewSortsFromUrl($this->tableViewSortsUrl);
        $this->tableViewSortsUrl = null;

        if ($this->restoreTableViewFromUrl($requested)) {
            return;
        }

        if ($this->requestHasTableViewsQueryState()) {
            return;
        }

        $before = $this->snapshotTableViewUrlProperties();
        $persisted = $this->snapshotPersistedTableViewParts();

        if (TableViewsOptions::resolve()->persistActiveViewInSession()) {
            $remembered = session()->get($this->getActiveTableViewSessionKey());

            if (filled($remembered) && $this->restoreRememberedTableView($remembered)) {
                $this->restorePersistedTableViewParts($persisted);
                $this->keepChangedTableViewUrlProperties($before);

                return;
            }
        }

        $this->applyStartupTableView();
        $this->restorePersistedTableViewParts($persisted);
        $this->keepChangedTableViewUrlProperties($before);
    }

    /**
     * What the USER left in the session for this table when the page loads, before a view is applied during mount: the column toggles and order, the pinned
     * filters and the sorts. Filament writes the session on every first request and a view apply writes it too, so a value only counts when it is not the
     * table default and not what the last applied view wrote (see markTableViewWrittenParts). Toggles are compared as maps: moving a column is not a toggle change.
     *
     * `orderMark` is the order the last view apply left in the session (null: none was applied), the baseline an order change is measured from.
     *
     * @return array{toggles: array<string, bool>|null, order: array<int, string>|null, orderMark: array<int, string>|null, pins: array{0: array<int, string>|null}|null, sort: array<int, array{column: string, direction: string}>|null}
     */
    protected function snapshotPersistedTableViewParts(): array
    {
        $table = $this->getTable();
        $toggles = $this->captureTableViewToggles();
        $order = $this->captureTableViewColumnOrder();
        $sorts = $this->effectiveTableViewSorts();
        $written = session()->get($this->getTableViewWrittenSessionKey());
        $written = is_array($written) ? $written : [];

        $togglesChanged = $table->persistsColumnsInSession()
            && $this->tableViewTogglesHash($toggles) !== $this->tableViewTogglesHash($this->defaultTableViewToggles())
            && $this->tableViewTogglesHash($toggles) !== ($written['columns'] ?? null);

        $orderMark = is_array($written['order'] ?? null) ? $written['order'] : null;

        $orderChanged = $table->persistsColumnsInSession()
            && $order !== []
            && $order !== $orderMark;

        $pinsChanged = $this->isTableViewQuickFiltersEnabled()
            && $this->tableViewFavoriteFilters !== null
            && md5(json_encode($this->tableViewFavoriteFilters)) !== ($written['pins'] ?? null);

        $sortChanged = $table->persistsSortInSession()
            && $sorts !== []
            && md5(json_encode($sorts)) !== ($written['sort'] ?? null);

        return [
            'toggles' => $togglesChanged ? $toggles : null,
            'order' => $orderChanged ? $order : null,
            'orderMark' => $orderMark,
            'pins' => $pinsChanged ? [$this->tableViewFavoriteFilters] : null,
            'sort' => $sortChanged ? $sorts : null,
        ];
    }

    /**
     * @param  array<string, bool>  $toggles
     */
    protected function tableViewTogglesHash(array $toggles): string
    {
        ksort($toggles);

        return md5(json_encode($toggles));
    }

    protected function getTableViewWrittenSessionKey(): string
    {
        return $this->getTableSessionKey('table_views_applied');
    }

    /**
     * Remembers what a view apply left in the session (column toggles and order, pinned filters and sorts, each as a hash), so a later page load can tell it
     * from a change the user made. A part the view preserved was not written by the view and keeps its earlier mark.
     *
     * @param  array<string>  $preserved
     */
    protected function markTableViewWrittenParts(array $preserved = []): void
    {
        $key = $this->getTableViewWrittenSessionKey();
        $written = session()->get($key);
        $written = is_array($written) ? $written : [];

        if (! in_array(PresetTableView::PART_COLUMNS, $preserved, true)) {
            $written['columns'] = $this->tableViewTogglesHash($this->captureTableViewToggles());
            $written['order'] = $this->captureTableViewColumnOrder();
        }

        if (! in_array(PresetTableView::PART_FILTERS, $preserved, true)) {
            $written['pins'] = md5(json_encode($this->tableViewFavoriteFilters));
        }

        if (! in_array(PresetTableView::PART_SORT, $preserved, true)) {
            $written['sort'] = md5(json_encode($this->effectiveTableViewSorts()));
        }

        session()->put($key, $written);
    }

    /**
     * A view applied while the page loads must not undo what the user changed after applying it and the session kept.
     * The view stays for everything that is not stored: its marker, a preset's hidden query and the parts the session knows nothing about.
     *
     * @param  array<string, mixed>  $persisted  see snapshotPersistedTableViewParts()
     */
    protected function restorePersistedTableViewParts(array $persisted): void
    {
        if ($this->activeTableViewId === null && $this->activeTablePresetKey === null) {
            return;
        }

        if ($persisted['toggles'] !== null || $persisted['order'] !== null) {
            $this->applyTableViewColumns($persisted['toggles'], $persisted['order']);
        }

        if ($persisted['pins'] !== null && $this->isTableViewQuickFiltersEnabled()) {
            $this->setTableViewFavoriteFilters($persisted['pins'][0]);
        }

        if ($persisted['sort'] !== null) {
            $this->setTableViewSorts($persisted['sort']);
            $this->updatedTableSort();
            $this->flushCachedTableRecords();
        }

        $this->rebaseTableViewOrder($persisted);
    }

    /**
     * A view that saved no order leaves the user's order in place, so the baseline taken while it was applied holds that order. When the session's order is a
     * change the user made after the last apply, the baseline has to be the order of that apply, or the view would stop showing as modified after a reload.
     *
     * @param  array<string, mixed>  $persisted  see snapshotPersistedTableViewParts()
     */
    protected function rebaseTableViewOrder(array $persisted): void
    {
        if ($persisted['order'] === null || $persisted['orderMark'] === null || ($this->activeTableViewId === null && $this->activeTablePresetKey === null)) {
            return;
        }

        $session = session();
        $key = $this->getTableViewWrittenSessionKey();
        $written = $session->get($key);

        if (is_array($written)) {
            $session->put($key, [...$written, 'order' => $persisted['orderMark']]);
        }

        $state = $this->captureTableViewState()->toArray();
        $state['columnOrder'] = $persisted['orderMark'];

        $this->activeTableViewHash = TableViewState::fromArray($state)->hash();
    }

    /**
     * The state read back from the URL replaces the view's columns, order and pins with what the page had before the view: the session's value when it
     * stored one, otherwise the table defaults. In the second case the view's own value is the better answer.
     *
     * @param  array<string, mixed>  $persisted  see snapshotPersistedTableViewParts()
     */
    protected function keepUnpersistedTableViewParts(array $persisted, TableViewState $viewState): void
    {
        $toggles = $persisted['toggles'] === null ? $viewState->toggledColumns : null;
        $order = $persisted['order'] === null ? $viewState->columnOrder : null;

        if ($toggles !== null || $order !== null) {
            $this->applyTableViewColumns($toggles, $order);
        }

        if ($persisted['pins'] === null && $this->isTableViewQuickFiltersEnabled()) {
            $this->setTableViewFavoriteFilters($viewState->favoriteFilters);
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
            $changed = match ($name) {
                'tableViewUrl' => $this->activeTablePresetKey !== null || $this->activeTableViewId !== null,
                'tableViewSortsUrl' => count($this->tableViewSorts) >= 2,
                default => ($before[$name] ?? null) !== json_encode($this->{$name}),
            };

            if ($changed) {
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
     * An empty `view=` means "no view, no startup view" (the user pressed Default); a non-empty one is validated by the restore, not counted here.
     */
    protected function requestHasTableViewsQueryState(): bool
    {
        foreach ($this->getTableViewUrlAttributes() as $name => $attribute) {
            if ($name === 'tableViewUrl') {
                if (request()->query($attribute->urlName()) === '') {
                    return true;
                }

                continue;
            }

            if (request()->query->has($attribute->urlName())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Livewire's legacy query-string hook: only a component whose own table state is URL-bound (ListRecords) carries the active view.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function queryStringHasTableViews(): array
    {
        return $this->hasUrlBoundTableState()
            ? ['tableViewUrl' => ['as' => 'view', 'history' => false], 'tableViewSortsUrl' => ['as' => 'sorts', 'history' => false]]
            : [];
    }

    public function dehydrateHasTableViews(): void
    {
        if (isset($this->table)) {
            // A sort list that no longer starts with the current sort is dead: forget it so it cannot come back when that sort is chosen again.
            $this->tableViewSorts = count($sorts = $this->effectiveTableViewSorts()) >= 2 ? $sorts : [];
            $this->persistTableViewSorts();
        }

        if (! $this->hasUrlBoundTableState()) {
            return;
        }

        $this->tableViewUrl = match (true) {
            $this->activeTablePresetKey !== null => 'preset:'.$this->activeTablePresetKey,
            $this->activeTableViewId !== null => 'user:'.$this->activeTableViewId,
            default => null,
        };

        $this->tableViewSortsUrl = isset($this->table) ? $this->serializeTableViewSortsForUrl() : null;
    }

    protected function hasUrlBoundTableState(): bool
    {
        return property_exists($this, 'tableFilters')
            && (new \ReflectionProperty($this, 'tableFilters'))->getAttributes(BaseUrl::class, \ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    /**
     * A reload of a URL-bound table keeps filters, sort and search from the URL. The view in the URL only brings back the active marker
     * and a preset's hidden query: the view is applied the normal way, then the table state read from the URL is put back on top.
     * Anything that is not a visible preset or one of the user's own views is ignored.
     *
     * @return bool true when a view was restored
     */
    protected function restoreTableViewFromUrl(mixed $value): bool
    {
        if (! is_string($value) || ! str_contains($value, ':')) {
            return false;
        }

        [$type, $key] = explode(':', $value, 2);

        $preset = $type === 'preset' ? ($this->resolveTablePresets()[$key] ?? null) : null;
        $view = $type === 'user' ? $this->findTableView($key) : null;

        if (! $preset && ! $view) {
            return false;
        }

        $urlState = $this->captureTableViewState();
        $persisted = $this->snapshotPersistedTableViewParts();

        $preset
            ? $this->activateTablePreset($preset, remember: false)
            : $this->activateTableView($view, remember: false);

        $viewState = $this->captureTableViewState();

        $this->applyTableViewState($urlState);
        $this->keepUnpersistedTableViewParts($persisted, $viewState);
        $this->rebaseTableViewOrder($persisted);

        return true;
    }

    /**
     * Presets defined by the developer. Declare it `protected` (or public) on the component.
     *
     * @return array<string, PresetTableView>
     */
    protected function getPresetTableViews(): array
    {
        return [];
    }

    public function applyTableView(mixed $viewId): void
    {
        $view = $this->findTableView($viewId);

        if ($view) {
            $this->activateTableView($view);
            $this->closeTableViewsManagerModal();
        }
    }

    public function applyTablePresetView(mixed $key): void
    {
        $preset = is_string($key) ? ($this->resolveTablePresets()[$key] ?? null) : null;

        if ($preset) {
            $this->activateTablePreset($preset);
            $this->closeTableViewsManagerModal();
        }
    }

    protected function activateTableView(TableView $view, bool $remember = true): void
    {
        $this->applyTableViewState($view->state);

        $this->activeTablePresetKey = null;
        $this->activeTableViewId = $view->getKey();
        $this->activeTableViewHash = $this->captureTableViewState()->hash();
        $this->markTableViewWrittenParts();

        if ($remember) {
            $this->rememberActiveTableView((string) $view->getKey());
        }
    }

    protected function activateTablePreset(PresetTableView $preset, bool $remember = true): void
    {
        $preserved = $preset->getPreservedParts();

        $this->resetTableViewParts(array_values(array_diff(PresetTableView::PARTS, $preserved)), keepColumnOrder: true);

        $apply = array_values(array_diff($preset->getDefinedParts(), $preserved));

        if ($apply !== []) {
            $this->applyTableViewState($this->buildTablePresetState($preset), $apply);
        }

        $this->activeTablePresetKey = $preset->getKey();
        $this->activeTableViewId = null;
        $this->activeTableViewHash = $this->captureTableViewState()->hash();
        $this->markTableViewWrittenParts($preserved);

        if ($remember) {
            $this->rememberActiveTableView('preset:'.$preset->getKey());
        }
    }

    public function applyDefaultTableView(): void
    {
        $preset = $this->resolveDeveloperDefaultPreset();

        if ($preset) {
            $this->resetTableViewColumnOrderToCode();
            $this->activateTablePreset($preset);
            $this->closeTableViewsManagerModal();

            return;
        }

        $this->resetTableViewParts(PresetTableView::PARTS);

        $this->activeTableViewId = null;
        $this->activeTableViewHash = null;
        $this->activeTablePresetKey = null;
        session()->forget($this->getTableViewWrittenSessionKey());

        $options = TableViewsOptions::resolve();

        if ($options->persistActiveViewInSession() && $options->managedDefaultViews()) {
            session()->put($this->getActiveTableViewSessionKey(), 'none');
        } else {
            $this->forgetActiveTableView();
        }
    }

    public function openTableViewsManager(): void
    {
        if (! TableViewsOptions::resolve()->viewManagerEnabled() || $this->tableViewsManagerLoaded) {
            return;
        }

        $this->tableViewsManagerLoaded = true;
        $this->forgetCachedTableViews();
    }

    public function toggleTableViewFavorite(mixed $type, mixed $key): void
    {
        if ($target = $this->normalizeTableViewTarget($type, $key)) {
            $this->changeTableViewPreferences(fn (TableViewPreferences $preferences): bool => $preferences->toggleFavorite(...$target));
        }
    }

    /**
     * Receives the whole new order of one manager list after a drag or a keyboard move: the keys of the views of `$type` in that favorite state.
     */
    public function reorderTableViews(mixed $type, mixed $favorite, mixed $keys): void
    {
        if (! TableViewsOptions::resolve()->viewManagerEnabled() || ! is_string($type) || ! is_bool($favorite)) {
            return;
        }

        $this->changeTableViewPreferences(fn (TableViewPreferences $preferences): bool => $preferences->reorder($type, $favorite, $keys));
    }

    public function moveTableView(mixed $type, mixed $key, mixed $direction): void
    {
        if (($target = $this->normalizeTableViewTarget($type, $key)) && is_string($direction)) {
            $this->changeTableViewPreferences(fn (TableViewPreferences $preferences): bool => $preferences->move(...$target, direction: $direction));
        }
    }

    public function toggleTableViewDefault(mixed $type, mixed $key): void
    {
        if (! TableViewsOptions::resolve()->managedDefaultViews()) {
            return;
        }

        if ($target = $this->normalizeTableViewTarget($type, $key)) {
            $this->changeTableViewPreferences(function (TableViewPreferences $preferences) use ($target): bool {
                $changed = $preferences->toggleDefault(...$target);

                if ($changed) {
                    $this->forgetActiveTableView();
                }

                return $changed;
            });
        }
    }

    protected function quickSaveTableViewAction(): Action
    {
        if ($this->isTableViewSaveBlockedByPreset()) {
            return QuickSaveTableViewAction::blocked();
        }

        return QuickSaveTableViewAction::make(
            $this->getTableViewsResourceKey(),
            fn (array $data): TableView => $this->saveTableView($data),
        );
    }

    protected function editTableViewAction(): Action
    {
        return EditTableViewAction::make(
            $this->getTableViewsResourceKey(),
            fn (mixed $viewId): ?TableView => $this->findTableView($viewId),
            fn () => $this->refreshTableViews(),
        );
    }

    protected function deleteTableViewAction(): Action
    {
        return DeleteTableViewAction::make(
            fn (mixed $viewId): ?TableView => $this->findTableView($viewId),
            function (TableView $view): void {
                $this->refreshTableViews();

                if ($this->activeTableViewId !== null && (string) $this->activeTableViewId === (string) $view->getKey()) {
                    $this->activeTableViewId = null;
                    $this->activeTableViewHash = null;
                    $this->forgetActiveTableView();
                }
            },
        );
    }

    protected function manageTableViewsAction(): Action
    {
        return ManageTableViewsAction::make(
            $this->editTableViewAction(),
            $this->deleteTableViewAction(),
        );
    }

    protected function getTableViewsResourceKey(): string
    {
        return static::class;
    }

    /**
     * Favorite user views for the bar, in the user's order.
     *
     * @return Collection<int, TableView>
     */
    protected function getTableViewsForBar(): Collection
    {
        return $this->getTableViewsCatalog()->favoriteUserViews()->map(fn ($item): TableView => $item->view)->values();
    }

    protected function getTableViewsCatalog(): TableViewCatalog
    {
        return $this->cachedTableViewCatalog ??= $this->buildTableViewCatalog();
    }

    protected function buildTableViewCatalog(bool $withPreferences = false): TableViewCatalog
    {
        $options = TableViewsOptions::resolve();
        $presets = $this->resolveTablePresets();

        $needsPreferences = $withPreferences
            || $presets !== []
            || ($options->managedDefaultViews() && $options->viewManagerEnabled() && ($this->tableViewsManagerLoaded || $this->isTableViewsSlideOverOpen()));

        return TableViewCatalog::build(
            $presets,
            TableView::forCurrentContext($this->getTableViewsResourceKey())->ordered()->get(),
            $needsPreferences ? TablePreference::forCurrentContext($this->getTableViewsResourceKey())->get() : collect(),
            $options->presetViewsManageable(),
            $options->newPresetViewSortPosition(),
        );
    }

    /**
     * Visible presets keyed by key, in developer order. Resolved once per request.
     *
     * @return array<string, PresetTableView>
     */
    protected function resolveTablePresets(): array
    {
        if ($this->cachedTablePresets !== null) {
            return $this->cachedTablePresets;
        }

        $presets = [];

        foreach ($this->getPresetTableViews() as $key => $preset) {
            $key = (string) $key;

            if (! $preset instanceof PresetTableView) {
                throw new InvalidArgumentException('getPresetTableViews() must return '.PresetTableView::class." instances, [{$key}] is not one.");
            }

            if (! preg_match('/^[A-Za-z0-9_.:-]{1,100}$/D', $key)) {
                throw new InvalidArgumentException("The preset table view key [{$key}] must be 1 to 100 letters, digits, dots, colons, dashes or underscores.");
            }

            $preset->key($key)->livewire($this)->cacheScope($this->getTableViewsResourceKey());

            if ($preset->isVisible()) {
                $presets[$key] = $preset;
            }
        }

        return $this->cachedTablePresets = $presets;
    }

    protected function resolveDeveloperDefaultPreset(): ?PresetTableView
    {
        $presets = $this->resolveTablePresets();

        foreach ($presets as $preset) {
            if ($preset->isDefault()) {
                return $preset;
            }
        }

        return $presets[TableViewCatalog::DEFAULT_KEY] ?? null;
    }

    /**
     * Called by the query scope the service provider adds to every table that uses this trait.
     */
    protected function applyActiveTablePresetQuery(Builder $query): Builder
    {
        if ($this->activeTablePresetKey === null) {
            return $query;
        }

        return ($this->resolveTablePresets()[$this->activeTablePresetKey] ?? null)?->applyQuery($query) ?? $query;
    }

    protected function isTableViewSaveBlockedByPreset(): bool
    {
        if ($this->activeTablePresetKey === null || TableViewsOptions::resolve()->createFromPresetWithQuery()) {
            return false;
        }

        return (bool) ($this->resolveTablePresets()[$this->activeTablePresetKey] ?? null)?->hasQuery();
    }

    protected function isActiveTableViewModified(): bool
    {
        if ($this->activeTableViewId === null && $this->activeTablePresetKey === null) {
            return false;
        }

        return $this->activeTableViewHash !== $this->captureTableViewState()->hash();
    }

    protected function captureTableViewState(): TableViewState
    {
        $sortColumn = $this->getTableSortColumn();
        $grouped = filled($this->tableGrouping);
        $sorts = $this->effectiveTableViewSorts();

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
            'sorts' => count($sorts) >= 2 ? $sorts : [],
            'toggledColumns' => $this->captureTableViewToggles(),
            'columnOrder' => $this->captureTableViewColumnOrder(),
            'favoriteFilters' => $this->isTableViewQuickFiltersEnabled() ? $this->tableViewFavoriteFilters : null,
            'grouping' => [
                'column' => $grouped ? (string) str($this->tableGrouping)->before(':') : null,
                'direction' => $grouped ? $this->getTableGroupingDirection() : null,
            ],
            'tab' => $this->shouldTrackTableViewTab() ? $this->activeTab : null,
        ]);
    }

    /**
     * Back to the table's own defaults for the given parts (see PresetTableView::PARTS).
     * The columns part also puts the column order back (Filament keeps order and visibility in one state), unless $keepColumnOrder is set.
     *
     * @param  array<string>  $parts
     */
    protected function resetTableViewParts(array $parts = PresetTableView::PARTS, bool $keepColumnOrder = false): void
    {
        $table = $this->getTable();

        $this->isTableReordering = false;

        if (in_array(PresetTableView::PART_FILTERS, $parts, true)) {
            $this->getTableFiltersForm()->fill();
            $this->applyTableViewFilters();
            $this->setTableViewFavoriteFilters(null);
        }

        if (in_array(PresetTableView::PART_SEARCH, $parts, true)) {
            $this->resetTableSearch();
            $this->resetTableColumnSearches();
        }

        if (in_array(PresetTableView::PART_SORT, $parts, true)) {
            $this->tableViewSorts = [];
            $this->tableSort = null;
            $this->updatedTableSort();
        }

        if (in_array(PresetTableView::PART_COLUMNS, $parts, true)) {
            $keepColumnOrder
                ? $this->applyTableViewColumns($this->defaultTableViewToggles())
                : $this->resetTableColumnManager();
        }

        if (in_array(PresetTableView::PART_GROUPING, $parts, true)) {
            $this->tableGrouping = $table->isDefaultGroupSelectable()
                ? $table->getDefaultGroup()->getId().':'.$table->getDefaultGroupDirection()
                : null;
            $this->updatedTableGrouping();
        }

        if (in_array(PresetTableView::PART_TAB, $parts, true) && $this->shouldTrackTableViewTab()) {
            $default = $this->getDefaultActiveTab();
            $this->activeTab = filled($default) ? (string) $default : null;
            $this->updatedActiveTab();
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    /**
     * @param  array<string>|null  $parts  null = every part
     */
    protected function applyTableViewState(TableViewState $state, ?array $parts = null): void
    {
        $parts ??= PresetTableView::PARTS;
        $table = $this->getTable();

        $this->isTableReordering = false;

        if (in_array(PresetTableView::PART_FILTERS, $parts, true)) {
            $filters = Arr::only($state->filters, array_keys($table->getFilters(withHidden: true)));
            $this->getTableFiltersForm()->fill($filters);
            $this->applyTableViewFilters();

            if ($this->isTableViewQuickFiltersEnabled()) {
                $this->setTableViewFavoriteFilters($state->favoriteFilters);
            }
        }

        if (in_array(PresetTableView::PART_SEARCH, $parts, true)) {
            $this->tableSearch = $state->search;
            $this->updatedTableSearch();

            $this->tableColumnSearches = $this->castTableColumnSearches($state->columnSearches);
            $this->fillReservedTableColumnSearchKeys();
            $this->updatedTableColumnSearches();
        }

        if (in_array(PresetTableView::PART_SORT, $parts, true)) {
            $this->setTableViewSorts($state->sortEntries());
            $this->updatedTableSort();
        }

        if (in_array(PresetTableView::PART_COLUMNS, $parts, true)) {
            $this->applyTableViewColumns($state->toggledColumns, $state->columnOrder);
        }

        if (in_array(PresetTableView::PART_GROUPING, $parts, true)) {
            $group = $state->groupingColumn !== null ? $table->getGroup($state->groupingColumn) : null;
            $this->tableGrouping = $group
                ? $group->getId().(in_array($state->groupingDirection, ['asc', 'desc'], true) ? ':'.$state->groupingDirection : '')
                : null;
            $this->updatedTableGrouping();
        }

        if (in_array(PresetTableView::PART_TAB, $parts, true) && $this->shouldTrackTableViewTab() && $state->tab !== null && array_key_exists($state->tab, $this->getCachedTabs())) {
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
    protected function mapTableViewColumnItems(array $items, Closure $map): array
    {
        return array_map(function (array $item) use ($map): array {
            if (($item['type'] ?? null) === self::TABLE_COLUMN_MANAGER_GROUP_TYPE) {
                $item['columns'] = $this->mapTableViewColumnItems($item['columns'] ?? [], $map);

                return $item;
            }

            return $map($item);
        }, $items);
    }

    /**
     * The preset's own values laid over the table defaults (the parts were just reset): filters per filter name, toggles against the real columns.
     */
    protected function buildTablePresetState(PresetTableView $preset): TableViewState
    {
        $data = $preset->toState()->toArray();

        $data['filters'] = array_replace($this->tableFilters ?? [], $data['filters']);

        if (($shown = $preset->getDefaultToggledColumns()) !== null) {
            $toggles = [];

            foreach (array_keys($this->defaultTableViewToggles()) as $name) {
                $toggles[$name] = in_array($name, $shown, true);
            }

            $data['toggledColumns'] = $toggles;
        }

        return TableViewState::fromArray($data);
    }

    protected function saveTableView(array $data): TableView
    {
        $userId = CurrentContext::userId();

        abort_if($userId === null, 403);

        if ($this->isTableViewSaveBlockedByPreset()) {
            Notification::make()
                ->title(__('table-views::quick-save.preset_with_query_title'))
                ->body(__('table-views::quick-save.preset_with_query_body'))
                ->warning()
                ->send();

            throw new Halt;
        }

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

        $this->forgetCachedTableViews();
        $this->activeTablePresetKey = null;
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
     * The first load: the user's managed default, then the developer default. A missing, hidden or deleted view falls through silently to the next step.
     * What this applies is not remembered in the session: only a view the user picked (or saved) is a choice.
     */
    protected function applyStartupTableView(): void
    {
        $options = TableViewsOptions::resolve();

        if ($options->managedDefaultViews() && $this->applyManagedDefaultTableView()) {
            return;
        }

        if ($preset = $this->resolveDeveloperDefaultPreset()) {
            $this->activateTablePreset($preset, remember: false);
        }
    }

    protected function applyManagedDefaultTableView(): bool
    {
        $row = TablePreference::forCurrentContext($this->getTableViewsResourceKey())
            ->where('is_default', true)
            ->where('is_hidden', false)
            ->orderBy('id')
            ->first();

        if (! $row) {
            return false;
        }

        if ($row->view_type === TablePreference::TYPE_PRESET) {
            $preset = $row->view_key === TableViewCatalog::DEFAULT_KEY ? null : ($this->resolveTablePresets()[$row->view_key] ?? null);

            if (! $preset) {
                return false;
            }

            $this->activateTablePreset($preset, remember: false);

            return true;
        }

        $view = $row->view_type === TablePreference::TYPE_USER ? $this->findTableView($row->view_key) : null;

        if (! $view) {
            return false;
        }

        $this->activateTableView($view, remember: false);

        return true;
    }

    /**
     * @return bool true when the remembered entry was handled (applied, or "none" = leave the table as is)
     */
    protected function restoreRememberedTableView(mixed $remembered): bool
    {
        if ($remembered === 'none') {
            return true;
        }

        if (is_string($remembered) && str_starts_with($remembered, 'preset:')) {
            $preset = $this->resolveTablePresets()[substr($remembered, 7)] ?? null;

            if ($preset) {
                $this->activateTablePreset($preset, remember: false);

                return true;
            }
        } elseif ($view = $this->findTableView($remembered)) {
            $this->activateTableView($view, remember: false);

            return true;
        }

        $this->forgetActiveTableView();

        return false;
    }

    protected function rememberActiveTableView(string $value): void
    {
        if (TableViewsOptions::resolve()->persistActiveViewInSession()) {
            session()->put($this->getActiveTableViewSessionKey(), ctype_digit($value) ? (int) $value : $value);
        }
    }

    protected function getActiveTableViewSessionKey(): string
    {
        return $this->getTableViewSessionKey('active');
    }

    protected function getTableViewSessionKey(string $name): string
    {
        return 'table-views.'.md5($this->getTableViewsResourceKey().'|'.CurrentContext::tenantType().'|'.CurrentContext::tenantId()).'.'.$name;
    }

    protected function forgetActiveTableView(): void
    {
        session()->forget($this->getActiveTableViewSessionKey());
    }

    protected function forgetCachedTableViews(): void
    {
        $this->cachedTableViewCatalog = null;
    }

    /**
     * Filament renders only the open modal while an action is mounted. A change made from the slide over also has to reach the favorites bar behind it.
     */
    protected function refreshTableViews(): void
    {
        $this->forgetCachedTableViews();

        if (($this->mountedActions ?? []) !== [] && method_exists($this, 'forceRender')) {
            $this->forceRender();
        }
    }

    /**
     * Type and key come from the browser. Returns [type, key] or null.
     *
     * @return array{type: string, key: string}|null
     */
    protected function normalizeTableViewTarget(mixed $type, mixed $key): ?array
    {
        if (! TableViewsOptions::resolve()->viewManagerEnabled()) {
            return null;
        }

        if (is_int($key)) {
            $key = (string) $key;
        }

        if (! in_array($type, [TablePreference::TYPE_PRESET, TablePreference::TYPE_USER], true) || ! is_string($key) || $key === '' || strlen($key) > 255) {
            return null;
        }

        if ($type === TablePreference::TYPE_USER && (strlen($key) > 18 || ! ctype_digit($key))) {
            return null;
        }

        return ['type' => $type, 'key' => $key];
    }

    /**
     * @param  Closure(TableViewPreferences): bool  $change
     */
    protected function changeTableViewPreferences(Closure $change): void
    {
        $this->forgetCachedTableViews();

        $change(new TableViewPreferences(
            $this->getTableViewsResourceKey(),
            $this->buildTableViewCatalog(withPreferences: true),
            TableViewsOptions::resolve(),
        ));

        $this->refreshTableViews();
    }

    protected function isTableViewsSlideOverOpen(): bool
    {
        return in_array(ManageTableViewsAction::NAME, array_column($this->mountedActions ?? [], 'name'), true);
    }

    protected function closeTableViewsManagerModal(): void
    {
        if (count($this->mountedActions ?? []) === 1 && $this->isTableViewsSlideOverOpen()) {
            $this->unmountAction();
        }
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
