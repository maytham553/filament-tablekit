# Views

A view is a saved table state with a name. Users open it again in one click.

## What a view holds

- filters
- global search and column searches
- sort (one column or several, see [Multi-sort](multi-sort.md))
- which toggleable columns are shown
- column order (see [Columns](columns.md))
- pinned quick filters (see [Quick filters](quick-filters.md))
- grouping
- the list page's active tab, only when `state.tab` is on

A view never holds a preset's hidden query. Records per page and the reordering switch are not saved either. Applying a view turns reordering off.

## The favorites bar

The bar sits in the table header. On table widgets it sits below the heading. Elsewhere it sits above the header content.

- **Default** resets the table to its own state (see [Default button](#the-default-button)).
- One button per favorite view, in the user's order.
- **Save view** opens Quick Save.
- The active view is highlighted. When the table changes after the view was applied, the view shows a "modified" mark.

The bar is drawn in the table header. A table with no header content (no heading, search, filters, grouping, column toggle or actions) shows no bar.

## Quick Save

Quick Save asks for:

- a **name**, unique per user, table and tenant (case-insensitive);
- a **color** and an optional **icon**, from the [pickers](colors-and-icons.md);
- whether to **add it to the favorites bar**.

Quick Save opens in a modal. Use `quickSaveSlideOver()` for a slide-over.

Quick Save is refused with an error on the name field when:

- the user already has `max_views_per_table` views for this table (default 100). Editing, deleting and applying are never blocked;
- the view is larger than `state.max_bytes` (default 64 KB);
- a preset with a hidden query is active. A notification explains it. Set `preset_views.create_from_preset_with_query` to allow it. The saved view then holds the visible state only.

## The Default button

- With a developer default preset (`->default()`, or a preset keyed `default`), it applies that preset.
- Otherwise it resets the table to Filament's own defaults.
- It also resets the column visibility and the column order.
- A preset keyed `default` replaces the button's label, icon, color, tooltip and badge.

## View manager

The manager button sits in the bar. `view_manager.position` puts it at the `end` (after Save view) or at the `start`. It opens a dropdown, or a slide-over with `view_manager.slide_over`.

The list has three sections. Empty sections are hidden.

1. **Favorites**: favorite presets first, then favorite user views, each in the user's order.
2. **My views**: user views that are not favorites.
3. **Preset views**: visible presets that are not favorites.

Each row lets the user:

- apply the view (click);
- add or remove it from favorites;
- set or remove it as default (only with managed defaults);
- edit or delete it (user views only);
- drag it to a new place inside its section. The handle shows when the section has two or more views. Favorite presets and favorite user views are two separate lists, so presets stay before user views.

A search box shows above 8 views. Use `view_manager.search` to force it on or off.

Edit uses the same checks as Quick Save. Delete asks for confirmation. Deleting the active view clears the active marker and leaves the table as it is.

How it behaves:

- The list loads only when the user opens the manager, so a closed manager costs no queries.
- Every change checks ownership on the server. View ids go through a scoped query and preset keys must belong to a visible preset. Anything else is silently ignored.
- In slide-over mode, a change (favorite, default, order, edit, delete) refreshes the bar behind it.
- The dropdown is fixed-positioned, so the table card cannot clip it. It has a 24rem maximum height and its own scroll.

### Drag and drop and keyboard

Dragging uses the `x-sortable` directive (SortableJS) that Filament already ships. There is nothing to install or load from a CDN.

- After a drop, the browser sends the whole new order of that list to the server. The server accepts it only when it holds exactly the keys the user may reorder in that list, each once. Anything else is ignored without an error.
- The handle is a focusable button. ArrowUp and ArrowDown on it move the row one place and keep the focus on the handle.
- When `preset_views.manageable` is `false`, users cannot reorder or unfavorite presets.

### Where choices are stored

- User views keep `is_favorite` and `sort` in `tablekit_views`.
- Presets keep their choices in `tablekit_preferences` (`is_favorite`, `sort`, `is_hidden`). `is_favorite` set to `null` means "use the preset's own default".
- `is_default` lives in `tablekit_preferences` for both kinds.
- A reorder writes the whole order of that kind.

## Default views and the start view

Turn on `managed_default_views` and each user can mark one view per table, a preset or their own, as their default.

On a fresh load of the table, with no table state in the URL, the start view is picked in this order:

1. The view the user chose last, remembered in the session. This needs `persist_active_view_in_session`. An explicit press of Default counts as a choice (stored as `none`).
2. The user's managed default.
3. The developer default preset (`->default()` or the preset keyed `default`). It applies even when managed defaults are off.
4. Filament's own defaults.

Rules to know:

- The session only holds views the user picked or saved. A view that the start step applied is not remembered as a choice.
- Choosing or removing a default in the manager is itself a new choice. It clears the remembered entry. The new default applies on the next fresh load. The view on screen stays as it is.
- A deleted view, or a preset that is hidden or removed, is skipped without an error.
- The default applies on every fresh load, not once per session.
- A start view overrides state Filament restored from `persist*InSession` (filters, search). The exception is state the user changed on their own: column visibility, column order, pinned filters and sorts are put back on top.
- What a view apply left in the session is not a user change. A changed default therefore brings its own columns, order, pins and sorts on the next visit. A value the user set back to exactly what the last view wrote, or to the table default, cannot be told apart from "never changed".
- Column toggles are compared as a map, so moving a column does not count as a change.

## Remembered view

With `persist_active_view_in_session`, the view the user last applied or saved is applied again on the next fresh load of that table.

- It is not applied when the URL already carries table state: `tableFilters`, `tableSearch`, `tableSortColumn`, `tableSortDirection`, `tableGrouping`, `tableGroupingDirection` or `activeTab`.
- A deleted view is forgotten.
- It is restored on reload only where the table state is URL-bound (a `ListRecords` page). Relation managers and table widgets have no URL, so only the session decides there.

When a view is applied on load (startup default or remembered view), the plugin asks the browser to always write the table state that the view set. A reload then shows the same screen, even after edits that land back on the view's values or clear them. Livewire would otherwise drop a URL parameter that equals its default after mount.

## The `?view=` URL marker

On a `ListRecords` page the active view is mirrored in the URL:

- `?view=preset:<key>` for a preset;
- `?view=user:<id>` for a user view;
- `?view=` (empty) after Default was pressed. It means "no view, do not apply the startup view".

The marker is written by the server and locked against Livewire updates.

On a fresh load the marker is read back through the same checks as applying a view. Only visible presets and the user's own views count. Anything else is ignored and the normal start view applies.

Then the table state in the URL (filters, sort, search, grouping, tab) is put back on top of the view. So the URL state wins, and only the active marker and a preset's hidden query come back. A link with `view` but no other table state restores the view, and it beats the startup default. Absent table parameters mean the table's own defaults.

Relation managers, table widgets and custom Livewire classes without `#[Url]` table state have no URL for the marker. A reload there starts from the usual start view.

## Styling

The bar uses Tailwind utility classes, so your panel theme must include them. The default Filament theme is enough. For a custom theme, add the plugin views to the Tailwind `content` list (see the README). The color and icon pickers bring their own inline styles.
