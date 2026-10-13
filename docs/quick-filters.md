# Quick filters

Quick filters turn the filter indicators into clickable chips. A chip opens a popover with that filter's own fields. Favorite filters stay visible even when they are not active.

Quick filters are **off by default**.

## Turn them on

| Where | How |
| --- | --- |
| Config | `quick_filters.enabled` set to `true` |
| Plugin | `TableKitPlugin::make()->quickFilters()` |
| One table | Override `public static function quickFiltersTableViews(): bool` on the component |

```php
public static function quickFiltersTableViews(): bool
{
    return true;
}
```

The bar needs a visible table header toolbar. A table with no search, filters, grouping or column toggle hides the toolbar, and the chip bar with it.

## What you see

When quick filters are on, **Filament's indicator bar is replaced** by a chip bar under the toolbar.

The new bar uses Filament's own indicator markup and classes (`fi-ta-filter-indicators`), so it looks the same. It has its own clear-all action. Filament has a `FILTER_INDICATORS` render hook, but it only fires when an indicator is active and cannot show an inactive favorite chip. So the plugin hides Filament's bar (`hiddenFilterIndicators()`) and draws the chips from the `TOOLBAR_AFTER` hook.
The table's own `hiddenFilterIndicators()` call is ignored while quick filters are on, so the two bars never show together.

The bar holds:

- the search and column search chips, exactly as Filament builds them, with the same remove buttons;
- one chip per filter that is **active** or **favorite**, in the order the table defines the filters;
- a clear-all action.

A filter is active when it has an indicator, when Filament counts it as active the way `QueryBuilder` does, or when its applied state holds a value. So a plain `schema()` filter without `indicateUsing` is covered too.

## Chips and popovers

- A chip opens a popover with that filter's own form group.
- A filter with several fields (such as a date range) is **one** chip that edits all its fields. Its label joins the indicators.
- Filament 4 defers filters by default. The popover then has an **Apply** button (`applyTableFilters`) and nothing is applied before it. With `deferFilters(false)`, changes apply at once.
- An outside click or Apply closes the popover.
- The x on a chip calls Filament's own `removeTableFilter()`. A favorite chip stays (inactive). Any other chip disappears.
- The x respects each indicator's `removable()` flag. When every indicator is removable, the whole filter is cleared. When only some are, the x removes just those (through their `removeField()`). When none is, there is no x.
- The x stops the mousedown, so it does not also toggle the popover.

### How the popover works

- The popover renders **only while its chip is open**. A closed bar costs no option queries, and a search keystroke or a sort adds nothing.
- When the panel becomes visible, its loading line calls `openTableViewQuickFilter` (`x-intersect`). So mouse, touch and keyboard all work. Filament's dropdown opens on mousedown and handles Enter and Space itself, so a click binding would never run for the keyboard.
- The open chip's form is a clone of the filter's group in its own schema (state path `tableViewQuickFilterState.<filter>.*`). Its DOM ids and wire keys never repeat the filter dropdown's, and a label click reaches the right field.
- Its changes are copied into `tableFilters` (or `tableDeferredFilters` when the table defers filters). The popover is refilled from that state on every render, so it always matches Filament's filter dropdown.
- Only the open chip's own filter is copied. Anything else the browser sends for that property is dropped.
- Popovers are filled from the filter state when they open, so a closed chip never loads options. A preloaded relationship select is queried once for the filter dropdown, and once more only while its chip is open.

## Favorite filters

`->favorite()` keeps a chip visible while its filter is inactive. It is a macro on Filament's filter base class, so it works on every filter. It accepts a bool or a closure.

```php
SelectFilter::make('status')->options([...])->favorite(),
TernaryFilter::make('is_paid')->quickFilterFavorite(),
```

`->quickFilterFavorite()` is the same call under the plugin's own name. It is always registered.

If another package already defines `BaseFilter::favorite()`, the plugin does **not** override it. It logs a warning at boot, and `quickFilterFavorite()` is the way to mark a filter.

### Pins

The star in the popover pins or unpins a filter for the user.

- The pins are part of the view state (`favoriteFilters`). They are stored only when they differ from the `favorite()` defaults, so a later change in code still reaches users.
- They are kept in the session, so a reload keeps them.
- Default and presets reset them. A preset sets its own with `defaultFavoriteFilters(['status', 'is_paid'])`.

## Shorter labels

`limitIndicatorLabels(2)` or `quick_filters.limit_labels` makes a multi-select chip show "Status: A, B & 3 more". `0` or `null` shows all values.

The label is shortened only when Filament's own label splits back into exactly the selected values, so it can never show something wrong.
