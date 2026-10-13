# Multi-sort

Multi-sort lets users sort a table by several columns. Each column has a priority.

## What users see

A **Sort** button (an icon with a count badge) sits in the table toolbar, next to the grouping selector. It opens a dropdown where the user can:

- add a sort column (only sortable, visible columns are offered);
- set each sort to ascending or descending;
- drag a sort to a new priority (a handle at the start of the row, shown with two or more sorts). ArrowUp and ArrowDown on the handle move it by keyboard;
- remove a sort.

The column headers of a multi-sort show a small priority number after the label.

The button shows only when the table has at least two sortable columns and a visible header toolbar. A table with no search, filters, grouping or column toggle hides the toolbar, and the Sort button with it.

## Settings

| Setting | Default | What it does |
| --- | --- | --- |
| `multi_sort.enabled` or `multiSort(true)` | `true` | Turns multi-sort on for every table that uses the trait. |
| `multi_sort.max` or `multiSort(true, max: 5)` | `5` | Most sort columns. Kept between 2 and 20. |

When the maximum is reached, the dropdown says so.

Turn it off for one table:

```php
public static function multiSortTableViews(): bool
{
    return false;
}
```

Turn it off everywhere with `multiSort(false)` or `multi_sort.enabled`.

## In views and presets

- A view stores the sorts as `sorts`, next to `sort`. `sort` is always the first entry of `sorts`. `sorts` appears only with two or more entries. See [Database](database.md#stored-state).
- A preset sets several sorts with an array. The first entry has the highest priority.

```php
PresetTableView::make()
    ->label('Big and new')
    ->defaultSort(['amount' => 'desc', 'ordered_at' => 'asc']);
```

## How it works with Filament 4

- The first sort is Filament's own (the `tableSort` string `column:direction`). The header arrow, the `sort` URL parameter, `persistSortInSession` and the mobile sort select keep working. The other sorts ride on it.
- Filament 4 applies a sort through `Column::applySort()` inside `applySortingToTableQuery()`. Every sortable column gets a thin wrapper around its own `sortable(query: ...)`. Custom sort closures, relationship columns and `sortable([...])` keep working.
- The extra sorts run in priority order inside Filament's own sort step. Grouping stays first, the table's default sort and key sort come after, and exports use the same order.
- The wrapper is installed on the same `Column` objects the column manager re-uses when the user reorders columns. Reordering columns never loses the multi-sort.
- The priority numbers use the `data-sort-priority` attribute. A `<style>` next to the Sort button targets Filament's `fi-ta-header-cell-sort-btn`.

## Rules

- A click on a column header keeps Filament's behavior and **replaces** the multi-sort with that single sort. The stored list only counts while it still starts with Filament's current sort.
- Adding the first sort to a table with a string `defaultSort('amount', 'desc')` keeps that sort as number 1 and appends the new one. A closure default has no column to extend, so the new sort becomes the first. Choosing the default column itself makes the default explicit.
- With `persistSortInSession()`, the extra sorts are kept in the session next to Filament's own sort. They are restored only when they start with the restored sort.
- While records are being reordered, the Sort button is replaced by a disabled one and the sort methods do nothing (Filament ignores sorts then).

## Reload and the URL

On a `ListRecords` page the extra sorts are mirrored in the URL:

```text
?sort=amount:desc&sorts=amount:desc,ordered_at:asc
```

The `sorts` value is written by the server and locked.

On a fresh load, the extra sorts are checked (sortable columns, direction, count, no duplicates). They are accepted only when they start with the sort the URL already carries. The plugin also asks the browser to always write the state a view set (see [Views](views.md#remembered-view)), so a startup view's multi-sort survives a reload.

Relation managers and table widgets have no URL, so extra sorts are lost on a full reload there.
