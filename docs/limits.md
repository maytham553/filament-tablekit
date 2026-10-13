# Limits

These are the known limits of TableKit for Filament 4.

## What a view can hold

- Records per page and the reordering switch (`isTableReordering`) are not saved. Applying a view turns reordering off.
- A saved view never contains a preset's hidden query.
- Each view can hold up to `state.max_bytes` (64 KB by default).
- A column added after a view was saved is shown or hidden by its own default. Names that are no longer toggleable columns are dropped.
- A column, column order, pin or sort that the user sets to exactly what the last applied view wrote (or to the table default) cannot be told apart from "never changed". The start view's own value then applies on the next load.

## Where it shows

- The favorites bar is drawn in the table header. A table with no header content (no heading, search, filters, grouping, column toggle or actions) shows no bar.
- The Sort button, the quick filter chips and the column list live in the table toolbar. Filament hides the toolbar when the table has no search, filters, grouping or column toggle. Then they are hidden too.
- The active view, the `?view=` marker and extra sorts are restored on reload only where the table state is URL-bound (a `ListRecords` or `ManageRelatedRecords` page). Relation managers, table widgets and custom Livewire classes without `#[Url]` table state have no URL, so the session alone decides there.

## Limits you set

- A user can save at most `max_views_per_table` views per table and tenant (default 100). Quick Save past the limit is refused with an error on the name field. Editing, deleting and applying are never blocked.
- The count and the insert are not one atomic step. Two saves at the same instant can pass the limit by one.
- Name uniqueness (per user, table and tenant, case-insensitive) is checked by validation. A database index does not enforce it. Case folding uses the database's `lower()` on both sides, so it follows the database collation.
- `multi_sort.max` is kept between 2 and 20. `quick_save.max_name_length` is kept between 1 and 100.

## Hidden query

- It is a filter, not access control. See [Presets](presets.md#hidden-query).
- On Filament 4, the hidden query also applies when a row action resolves its record, including on a many-to-many relation manager (Filament passes the table query scopes with `isResolvingRecord`). A preset closure cannot opt out of that.
- Quick Save is refused while a preset with a hidden query is active, unless you allow it. The saved view then holds the visible state only.

## Presets and defaults

- There is no UI to hide a preset. `is_hidden` is honored when it is set in the database.
- Two tabs that set a default at the same instant can leave two default rows for a user. The earliest wins at startup.
- Preference rows of presets that you removed from the code stay until the user's rows are deleted.
- Both of these are harmless and are not cleaned up.

## Columns

- Only tables with `reorderableColumns()` and no columns layout (Split, Stack, Panel) have an order to save.
- A view saved without a column order keeps the user's order when applied. If the user then moves a column and reloads, the view still shows as modified, because the plugin remembers the order of the last apply in the session. A user who cleared the session, or never applied a view in it, sees the view as clean.

## Quick filters

- The table's own `hiddenFilterIndicators()` call is ignored while quick filters are on.
- If another package already defines `BaseFilter::favorite()`, use `quickFilterFavorite()` instead.

## Browser behavior and tests

- The Alpine behavior (the dropdown, drag and drop, keyboard reorder, the slide-over, the chip popover, the sort list and the priority numbers) is covered by server-side tests. It was exercised once by hand in a browser: dropdown, apply, keyboard and mouse reorder, chip open by mouse and keyboard, Apply, sort list keyboard reorder, priority numbers, and reload on the `sorts` URL. There is no browser test.
- Mouse drag of the sort list, dark mode and RTL were not checked in that manual pass.

## Internals

- The trait's lifecycle hooks (`mountHasTableKit`, `bootedHasTableKit`, `renderingHasTableKit`) are public because Livewire needs that. A browser request that names them is ignored by `callHasTableKit`.
- The plugin views use inline styles and Filament's `fi-*` classes only, so they do not depend on your theme. A test forbids other utility classes in the views.
