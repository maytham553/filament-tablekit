<?php

namespace TableKit\Concerns;

use Filament\Forms\Form;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Arr;
use Livewire\Attributes\Locked;
use TableKit\Support\FavoriteFilters;
use TableKit\Support\TableKitOptions;

/**
 * Clickable filter chips. Filament's own indicator bar is hidden and the chip bar renders instead:
 * one chip per active or favorite filter. A chip's popover renders only while it is open: a clone of that filter's form group in a form of its own
 * (own state path, so own DOM ids and wire keys), whose changes are copied into the table's filter state.
 */
trait HasQuickFilterTableViews
{
    /** The pinned filters of this user. Null = the filters marked with `favorite()`. Written on the server only. */
    #[Locked]
    public ?array $tableViewFavoriteFilters = null;

    /** The chip whose popover is rendered right now. Server state: set only by openTableViewQuickFilter. */
    #[Locked]
    public ?string $tableViewOpenQuickFilter = null;

    /** State of the open popover's form: `[filter name => its fields]`. Copied into the table's filter state on every change. */
    public array $tableViewQuickFilterState = [];

    /** Return false from a component to keep Filament's own indicator bar for its table. */
    public static function quickFiltersTableViews(): bool
    {
        return true;
    }

    protected function isTableViewQuickFiltersEnabled(): bool
    {
        return static::quickFiltersTableViews()
            && TableKitOptions::resolve()->quickFiltersEnabled()
            && count($this->getTable()->getFilters()) > 0;
    }

    protected function bootTableViewQuickFilters(): void
    {
        if (! $this->isTableViewQuickFiltersEnabled()) {
            return;
        }

        // Always hidden while quick filters are on, whatever the table's own call said: the chip bar replaces it, so the two never show together.
        $this->getTable()->hiddenFilterIndicators();

        if ($this->tableViewOpenQuickFilter !== null) {
            $this->cacheTableViewQuickFilterForm();
        }

        if (! $this->shouldMountHasTableKit) {
            return;
        }

        $saved = session()->get($this->getTableViewSessionKey('favorite_filters'));

        $this->tableViewFavoriteFilters = is_array($saved) ? $this->normalizeTableViewFavoriteFilters($saved) : null;
    }

    /**
     * @return array<int, string> the pinned filters in the order the table defines them
     */
    protected function effectiveTableViewFavoriteFilters(): array
    {
        return $this->tableViewFavoriteFilters === null
            ? FavoriteFilters::defaults($this->getTable())
            : $this->orderTableViewFavoriteFilters($this->tableViewFavoriteFilters);
    }

    /**
     * Visible filters only, in table order.
     *
     * @param  array<int, mixed>  $names
     * @return array<int, string>
     */
    protected function orderTableViewFavoriteFilters(array $names): array
    {
        $names = array_map('strval', array_filter($names, 'is_string'));
        $list = [];

        foreach ($this->getTable()->getFilters() as $name => $filter) {
            if (in_array((string) $name, $names, true)) {
                $list[] = (string) $name;
            }
        }

        return $list;
    }

    /**
     * Null when the list equals the developer's favorites, so a later change in code still reaches the user.
     *
     * @param  array<int, mixed>|null  $names
     * @return array<int, string>|null
     */
    protected function normalizeTableViewFavoriteFilters(?array $names): ?array
    {
        if ($names === null) {
            return null;
        }

        $list = $this->orderTableViewFavoriteFilters($names);

        return $list === FavoriteFilters::defaults($this->getTable()) ? null : $list;
    }

    protected function setTableViewFavoriteFilters(?array $names): void
    {
        $this->tableViewFavoriteFilters = $this->normalizeTableViewFavoriteFilters($names);

        if ($this->tableViewFavoriteFilters === null) {
            session()->forget($this->getTableViewSessionKey('favorite_filters'));
        } else {
            session()->put($this->getTableViewSessionKey('favorite_filters'), $this->tableViewFavoriteFilters);
        }
    }

    public function togglePinnedTableViewFilter(mixed $name): void
    {
        if (! $this->isTableViewQuickFiltersEnabled() || ! is_string($name) || ! isset($this->getTable()->getFilters()[$name])) {
            return;
        }

        $favorites = $this->effectiveTableViewFavoriteFilters();

        $this->setTableViewFavoriteFilters(
            in_array($name, $favorites, true) ? array_values(array_diff($favorites, [$name])) : [...$favorites, $name],
        );
    }

    public function openTableViewQuickFilter(mixed $name): void
    {
        if (! $this->isTableViewQuickFiltersEnabled() || ! is_string($name) || ! isset($this->getTable()->getFilters()[$name])) {
            return;
        }

        $this->tableViewOpenQuickFilter = $name;
        $this->cacheTableViewQuickFilterForm();
    }

    /**
     * @param  mixed  $name  only closes when it is still the open chip, so a late close of one chip cannot shut the next one
     */
    public function closeTableViewQuickFilter(mixed $name = null): void
    {
        if ($name !== null && $name !== $this->tableViewOpenQuickFilter) {
            return;
        }

        $this->tableViewOpenQuickFilter = null;
        $this->tableViewQuickFilterState = [];
        $this->cacheForm('tableViewQuickFilterForm', null);
    }

    /**
     * Copies the popover's fields of the open filter into the table's filter state (the deferred one on a deferred table), the way Filament's own filter form writes it.
     */
    public function updatedTableViewQuickFilterState(mixed $value = null, mixed $key = null): void
    {
        $name = $this->tableViewOpenQuickFilter;
        $group = $name !== null ? ($this->tableViewQuickFilterState[$name] ?? null) : null;

        if ($name === null || ! is_array($group) || ! $this->isTableViewQuickFiltersEnabled() || (is_string($key) && ! str_starts_with($key.'.', $name.'.'))) {
            $this->tableViewQuickFilterState = $name !== null && is_array($group) ? [$name => $group] : [];

            return;
        }

        $this->tableViewQuickFilterState = [$name => $group];

        if ($this->getTable()->hasDeferredFilters()) {
            $this->tableDeferredFilters[$name] = $group;

            return;
        }

        $this->tableFilters[$name] = $group;
        $this->updatedTableFilters();
    }

    protected function cacheTableViewQuickFilterForm(): void
    {
        $name = $this->tableViewOpenQuickFilter;
        $group = $name === null ? null : $this->getTableFiltersForm()->getComponent($name);

        if ($group === null) {
            $this->cacheForm('tableViewQuickFilterForm', null);

            return;
        }

        $this->cacheForm('tableViewQuickFilterForm', fn (): Form => $this->makeForm()
            ->schema([$group->getClone()])
            ->model($this->getTable()->getModel())
            ->statePath('tableViewQuickFilterState')
            ->when(! $this->getTable()->hasDeferredFilters(), fn (Form $form): Form => $form->live()));
    }

    /**
     * The open chip's form, filled from the current filter state.
     */
    protected function getTableViewQuickFilterForm(string $name): ?Form
    {
        if ($name !== $this->tableViewOpenQuickFilter || ! $this->hasCachedForm('tableViewQuickFilterForm')) {
            return null;
        }

        $state = $this->getTable()->hasDeferredFilters() ? $this->tableDeferredFilters : $this->tableFilters;
        $form = $this->getForm('tableViewQuickFilterForm');

        $form->fill([$name => $state[$name] ?? []]);

        return $form;
    }

    public function removeTableViewQuickFilter(mixed $name): void
    {
        $filter = is_string($name) ? ($this->getTable()->getFilters()[$name] ?? null) : null;

        if (! $this->isTableViewQuickFiltersEnabled() || $filter === null) {
            return;
        }

        $removable = [];
        $locked = [];

        foreach ($filter->getIndicators() as $indicator) {
            $field = $indicator->getRemoveField();

            if (blank($field)) {
                continue;
            }

            if ($indicator->isRemovable()) {
                $removable[] = $field;
            } else {
                $locked[] = $field;
            }
        }

        $fields = array_values(array_diff(array_unique($removable), $locked));

        if ($fields === []) {
            return;
        }

        foreach ($fields as $field) {
            $this->removeTableFilter($name, $field, isRemovingAllFilters: true);
        }

        $this->getTable()->hasDeferredFilters() ? $this->applyTableFilters() : $this->handleTableFilterUpdates();
    }

    /**
     * Chips for the search and the column searches, built the way Filament builds its own indicators.
     *
     * @return array<int, Indicator>
     */
    protected function getTableViewSearchIndicators(): array
    {
        $table = $this->getTable();

        $indicators = [
            ...($table->hasSearch() ? [$table->getSearchIndicator()] : []),
            ...$table->getColumnSearchIndicators(),
        ];

        return array_values(array_filter(array_map(
            fn (mixed $indicator): ?Indicator => $indicator instanceof Indicator ? $indicator : (is_string($indicator) ? Indicator::make($indicator) : null),
            $indicators,
        )));
    }

    /**
     * One entry per filter that is active (an indicator, Filament's own count as QueryBuilder has it, or a value in its applied state) or pinned, in the order the table defines the filters.
     * `remove`: `all` clears the whole filter, `partial` clears only the indicators Filament marks removable, null shows no remove button.
     *
     * @return array<int, array{name: string, label: mixed, color: string, active: bool, favorite: bool, remove: string|null, filter: BaseFilter}>
     */
    protected function getTableViewQuickFilterChips(): array
    {
        $favorites = $this->effectiveTableViewFavoriteFilters();
        $chips = [];

        foreach ($this->getTable()->getFilters() as $name => $filter) {
            $name = (string) $name;
            $indicators = $filter->getIndicators();
            $active = $indicators !== [] || $filter->getActiveCount() > 0 || $this->hasTableViewFilterState($name);
            $favorite = in_array($name, $favorites, true);

            if (! $active && ! $favorite) {
                continue;
            }

            $chips[] = [
                'name' => $name,
                'label' => $indicators !== [] ? $this->getTableViewChipLabel($name, $filter, $indicators) : $filter->getLabel(),
                'color' => $indicators !== [] ? ($indicators[array_key_first($indicators)]->getColor() ?? 'primary') : ($active ? 'primary' : 'gray'),
                'active' => $active,
                'favorite' => $favorite,
                'remove' => $active ? $this->getTableViewChipRemoveMode($indicators) : null,
                'filter' => $filter,
            ];
        }

        return $chips;
    }

    /**
     * Filters that define no indicator (a plain `form()` filter) still count as active while their applied state holds a value that is not the field's own
     * default: Filament fills the defaults on the first load, and that alone is not something the user applied.
     */
    protected function hasTableViewFilterState(string $name): bool
    {
        $state = $this->getTableFilterState($name);

        if (! is_array($state)) {
            return false;
        }

        $defaults = Arr::dot($this->getTableViewFilterDefaultState($name));

        foreach (Arr::dot($state) as $key => $value) {
            if (blank($value) || $value === false) {
                continue;
            }

            $default = $defaults[$key] ?? null;

            if (! (is_scalar($value) && is_scalar($default) && (string) $value === (string) $default)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed> the defaults of the filter's own form fields
     */
    protected function getTableViewFilterDefaultState(string $name): array
    {
        $defaults = [];

        foreach ($this->getTableFiltersForm()->getComponent($name)?->getChildComponentContainer()->getFlatFields(withHidden: true) ?? [] as $key => $field) {
            Arr::set($defaults, (string) $key, $field->getDefaultState());
        }

        return $defaults;
    }

    /**
     * @param  array<int|string, Indicator>  $indicators
     */
    protected function getTableViewChipRemoveMode(array $indicators): ?string
    {
        $removable = array_filter($indicators, fn (Indicator $indicator): bool => $indicator->isRemovable());

        return match (true) {
            count($removable) === count($indicators) => 'all',
            collect($removable)->contains(fn (Indicator $indicator): bool => filled($indicator->getRemoveField())) => 'partial',
            default => null,
        };
    }

    /**
     * @param  array<int|string, Indicator>  $indicators
     */
    protected function getTableViewChipLabel(string $name, BaseFilter $filter, array $indicators): mixed
    {
        $labels = array_values(array_map(fn (Indicator $indicator): mixed => $indicator->getLabel(), $indicators));

        if (count($labels) === 1) {
            return $this->shortenTableViewIndicatorLabel($name, $filter, $labels[0]);
        }

        return implode(' · ', array_map(fn (mixed $label): string => strip_tags((string) $label), $labels));
    }

    /**
     * "Status: A, B & 3 more". Only done when Filament's own label splits back into exactly the selected values, so it can never show something wrong.
     */
    protected function shortenTableViewIndicatorLabel(string $name, BaseFilter $filter, mixed $label): mixed
    {
        $limit = TableKitOptions::resolve()->limitIndicatorLabels();
        $values = $this->getTableFilterState($name)['values'] ?? null;

        if ($limit === null || ! is_string($label) || ! $filter instanceof SelectFilter || ! $filter->isMultiple() || ! is_array($values) || count($values) <= $limit || ! str_contains($label, ': ')) {
            return $label;
        }

        [$head, $list] = explode(': ', $label, 2);
        $items = explode(', ', str_replace(' & ', ', ', $list));

        if (count($items) !== count($values)) {
            return $label;
        }

        return $head.': '.implode(', ', array_slice($items, 0, $limit)).' '.__('tablekit::quick-filters.more', ['count' => count($items) - $limit]);
    }
}
