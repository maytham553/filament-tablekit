# Limits

These are the known limits of TableKit for Filament 3.

## What a view can hold

- Records per page and the reordering switch (`isTableReordering`) are not saved. Applying a view turns reordering off.
- A saved view never contains a preset's hidden query.
- Each view can hold up to `state.max_bytes` (64 KB by default).
- A column added after a view was saved is shown or hidden by its own default. Names that are no longer toggleable columns are dropped.
- A column, column order, pin or sort that the user sets to exactly what the last applied view wrote (or to the table default) cannot be told apart from "never changed". The start view's own value then applies on the next load.

## Where it shows

- The favorites bar is drawn in the table header. A table with no header content (no heading, search, filters, grouping, column toggle or actions) shows no bar.
- The Sort button, the quick filter chips and the column list live in the table toolbar. Filament hides the toolbar when the table has no search, filters, grouping or column toggle. Then they are hidden too.
- The active view, the `?view=` marker and extra sorts are restored on reload only where the table state is URL-bound (a `ListRecords` page). Relation managers, table widgets and custom Livewire classes without `#[Url]` table state have no URL, so the session alone decides there.

## Limits you set

- A user can save at most `max_views_per_table` views per table and tenant (default 100). Quick Save past the limit is refused with an error on the name field. Editing, deleting and applying are never blocked.
- The count and the insert are not one atomic step. Two saves at the same instant can pass the limit by one.
- Name uniqueness (per user, table and tenant, case-insensitive) is checked by validation. A database index does not enforce it. Case folding uses the database's `lower()` on both sides, so it follows the database collation.
- `multi_sort.max` is kept between 2 and 20. `quick_save.max_name_length` is kept between 1 and 100.

## Hidden query

- It is a filter, not access control. See [Presets](presets.md#hidden-query).
- On a many-to-many relation manager, Filament resolves a row action's record (`getTableRecord`) straight from the relationship, not through the table query. So an active preset's hidden query does not limit which related record a row action can reach. The same holds for bulk actions when the relation allows duplicate pivot rows. Listing, counts, exports, summaries and other bulk actions do honor it. A plugin trait cannot scope that path without overriding Filament's own method, which collides with `InteractsWithTable`.
- Quick Save is refused while a preset with a hidden query is active, unless you allow it. The saved view then holds the visible state only.

## Presets and defaults

- There is no UI to hide a preset. `is_hidden` is honored when it is set in the database.
- Two tabs that set a default at the same instant can leave two default rows for a user. The earliest wins at startup.
- Preference rows of presets that you removed from the code stay until the user's rows are deleted.
- Both of these are harmless and are not cleaned up.

## Columns

- Only tables with a flat list of columns are supported. Column groups, layout components (Split, Stack, Panel) and collapsible layouts are left alone.
- A component that calls Filament's `resetTable()` rebuilds the table without the custom column order and multi-sort for the rest of that request.

## Quick filters

- A quick filter popover is an extra round trip on open, because its form is rendered lazily.
- An `afterStateUpdated` on a filter field runs on the popover's own copy of the form.
- The table's own `hiddenFilterIndicators()` call is ignored while quick filters are on.
- If another package already defines `BaseFilter::favorite()`, use `quickFilterFavorite()` instead.

## Browser behavior and tests

- The Alpine behavior (the dropdowns of the manager, the Sort button, the quick filter popovers, the column list and the header priority numbers) is covered by server-side tests only. There is no browser test. That includes opening a chip (the popover shows a spinner until the server renders its form), closing it on an outside click, and label clicks inside a popover.

## Internals

- The trait's lifecycle hooks (`mountHasTableKit`, `bootedHasTableKit`, `renderingHasTableKit`) are public because Livewire needs that. A browser request that names them is ignored by `callHasTableKit`.
- The bar's utility classes must be covered by the panel theme. The default Filament theme is enough. The color and icon pickers bring their own inline styles.
