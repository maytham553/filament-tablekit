# elite/filament-table-views

Saved table views for Filament v3: save the current filters, search, column searches, sort, toggled columns and grouping as a named view, and reload it in one click from a favorites bar above the table.

Phase 2 adds developer-defined **preset views** (with an optional hidden query), a **view manager** (favorites, order, edit, delete) and a **managed default view** per user.

Phase 3 adds three table power tools, all saved in views: **multi-sort** (sort by several columns), **quick filters** (clickable filter chips, favorite filters) and **column reordering**. No migration is needed: they extend the stored state JSON.

Requires PHP 8.2+, Laravel 11, Filament 3.3+.

## Install

```bash
composer require elite/filament-table-views
php artisan migrate
```

Two migrations are loaded automatically: `table_views` (Phase 1) and `table_view_preferences` (Phase 2: per-user favorites, order and default). If your app owns its migrations, set `load_migrations` to `false` in the config and publish them instead (the tag publishes both files):

```bash
php artisan vendor:publish --tag=table-views-migrations
php artisan vendor:publish --tag=table-views-config   # optional
```

## Register the plugin

```php
use Elite\TableViews\TableViewsPlugin;

$panel->plugin(
    TableViewsPlugin::make()
        ->favoritesBar()                     // bool|Closure, default from config
        ->quickSaveSlideOver()               // modal by default
        ->colors(['primary', 'success'])     // Filament color names offered in Quick Save
        ->icons(['star', 'flag'])            // subset of the icon keys below
        ->userModel(App\Models\User::class) // only used by TableView::user()
        ->persistActiveViewInSession()
        ->connection('kyc')                  // wins over config('table-views.connection')
);
```

Phase 2 options (all optional, the plugin wins over config):

```php
TableViewsPlugin::make()
    ->viewManager(enabled: true, slideOver: false, position: 'end')  // 'start' | 'end'
    ->presetViewsManageable(true)       // false = users cannot reorder/unfavorite presets
    ->newPresetViewSortPosition('after') // where presets the user has not ranked yet go: 'before' | 'after'
    ->presetViewLockIcon(true)          // lock icon on preset rows in the manager
    ->favoritesBarDivider(true)         // divider between presets and the user's views
    ->managedDefaultViews(false)        // users can mark one view per table as their default
    ->createFromPresetWithQuery(false)  // allow Quick Save while a preset with a hidden query is active
```

Phase 3 options (all optional, the plugin wins over config):

```php
TableViewsPlugin::make()
    ->multiSort(enabled: true, max: 5)   // default on; max 2..20 sort columns
    ->quickFilters()                      // default OFF
    ->limitIndicatorLabels(2)             // multi-select chip: "A, B & 3 more". 0 or null = show all
    ->columnReordering()                  // default on
```

Every option is optional and falls back to `config/table-views.php`. The favorites bar also works without registering the plugin (config only).

The table name is config only (`table-views.table`) because the migration reads it too.

## User key

`user_id` is a string column (64 chars), so integer, ULID and UUID user keys all work with no setting. Views are scoped by the auth identifier (`Filament::auth()->id()`) compared as a string. There is no foreign key to the users table.

## Database connection

`connection` is `null` by default, which means the app's default connection **at the moment of use**. If your app changes `database.default` per panel or module (for example one connection per module), views would then be read from a different database than the one you migrated. In that case pin the connection, either in config or per panel (the plugin value wins):

```php
// config/table-views.php
'connection' => 'kyc',

// or per panel
TableViewsPlugin::make()->connection('kyc')
```

The migration must exist on that connection. The migration uses `config('table-views.connection')`, or the default connection when it is `null`, so for a per-panel connection run it against that connection:

```bash
php artisan migrate --database=kyc
```

Each connection that serves a panel with the plugin needs its own `table_views` table (and `migrations` table row).

## Add the trait

On a List/Manage page, a relation manager or a table widget:

```php
use Elite\TableViews\Concerns\HasTableViews;

class ListOrders extends ListRecords
{
    use HasTableViews;
}
```

Views are stored per user, per tenant (`Filament::getTenant()`) and per component class. Override the protected `getTableViewsResourceKey()` to change the key (for example to share views between two classes).

Declare `InteractsWithTable` (already part of `ListRecords`, relation managers and `TableWidget`) before `HasTableViews` in a custom Livewire class. The remembered-view restore is skipped when the table is not ready yet.

## Preset views

Return presets from the component that uses the trait. Declare the method `protected` (a `public` one is callable from the browser; it only returns the presets, but there is no reason to expose it):

```php
use Elite\TableViews\Presets\PresetTableView;

protected function getPresetTableViews(): array
{
    return [
        'shipped' => PresetTableView::make()
            ->label(__('Shipped'))
            ->icon('truck')                    // icon KEY, resolved through the table-views::icons.<key> alias
            ->color('success')
            ->favorite()                       // shown in the favorites bar unless the user unfavorites it
            ->defaultFilters(['status' => ['value' => 'shipped']])   // raw tableFilters, per filter name
            ->defaultSort('ordered_at', 'desc')                      // or ['amount' => 'desc', 'ordered_at' => 'asc']: a multi-sort, first = highest priority
            ->defaultSearch('foo')
            ->defaultColumnSearches(['reference' => 'ORD'])
            ->defaultToggledColumns(['notes', 'customer.city'])      // the toggleable columns that are shown (order untouched)
            ->defaultColumns(['status', 'amount', 'notes'])          // order AND visibility: listed columns first, in this order
            ->defaultFavoriteFilters(['status', 'is_paid'])          // the filters pinned as quick filter chips
            ->defaultGrouping('status', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->where(...))  // hidden query, only while this preset is active
            ->badge(fn () => Order::where(...)->count())->badgeColor('warning')->cacheBadgeFor(60)   // cacheBadgeFor: optional, in seconds
            ->tooltip('Orders on the way')
            ->visible(fn () => auth()->user()->can('...'))           // and ->hidden()
            ->default()                                              // or ->default(fn () => bool)
            ->preserveAll(),                   // or preserveFilters() / preserveToggledColumns() / preserveSort() / preserveSearch()
                                               // preserveFilters() also keeps the pinned filters, preserveToggledColumns() also keeps the column order
    ];
}
```

- Keys are 1 to 100 letters, digits, `_ . : -`. A wrong value or key throws.
- Applying a preset resets every part to the table defaults, then applies the parts the preset defines. Parts it keeps with `preserve*` are left as they are. A preset that defines nothing is a plain reset.
- Filters are laid over the table's filter defaults, per filter name. `defaultFilters([])` therefore keeps the defaults; name a filter to change it.
- `modifyQueryUsing` is active only while the preset is the active view. It is kept in locked server state (`activeTablePresetKey`), so it survives Livewire requests, the browser cannot set it, and it ends when another view or Default is applied. It also applies to the filtered query, exports and summaries. It stops applying if the preset is hidden or removed. It is added through `Table::configureUsing()`, so no change to the component's `table()` is needed.
- The `modifyQueryUsing` closure runs inside a nested where group on a fresh builder of the table's model, so `orWhere` inside it can never escape the table's own constraints (tenant scope, relation manager parent, Filament filters and search). Only where constraints are carried over (model scopes and `whereHas` work); ordering belongs in `defaultSort()`, joins and eager loads in the table query. The closure runs every time the table query is built, several times per request: keep it cheap and free of side effects.
- **A hidden preset query is a filter, not an access-control boundary.** The user can drop it at any time (apply their own view, press Default, pick another preset), so never use it to hide data they must not see. Put authorization in the resource query, policies or global scopes.
- **Reload**: on a `ListRecords` page the active view is mirrored in the URL as `?view=preset:<key>` or `?view=user:<id>` (server-written, locked against Livewire updates). On a fresh load it is read back through the same checks as applying (visible presets only, the user's own views only, anything else is ignored and the normal start view applies). The view is applied, then the filters, sort, search and so on read from the URL are put back on top, so the URL state wins and only the active marker and a preset's hidden query return. A link with `view` but no other table state also restores the view, and it beats the startup default. Absent table parameters mean the table's own defaults, and an empty `?view=` means "no view, do not apply the startup view" (written after Default is clicked). When a view is applied on the first load (startup default, remembered view), the plugin asks the browser to always write the table state that view set, so a reload shows the same screen even after edits that land back on the view's values or clear them.
- Quick Save while such a preset is active shows a notification instead (the hidden query could not be part of the saved view). Set `preset_views.create_from_preset_with_query` to allow it: the saved view then holds the visible state only.
- The preset keyed `default` replaces the built-in Default button (label, icon, color, tooltip, badge) and is the implicit developer default.
- `badge()`, `badgeColor()` and `tooltip()` closures run at most once per request, only for presets shown in the bar and only when the bar renders. Every Livewire request re-renders the bar (search, sort, pagination, polling), so a badge that queries still runs on each of them: add `->cacheBadgeFor($seconds)` to keep the value in the app cache per user, tenant and table.
- Custom icon keys: register `FilamentIcon::register(['table-views::icons.rocket' => '...'])`. An unknown key shows no icon.
- Presets of a component are ordered as returned, unless the user reordered them in the manager.

## Multi-sort

A **Sort** button (icon with a count badge) sits in the table toolbar next to the grouping selector. It opens a dropdown where the user adds a sort column (only sortable, visible columns), sets each one ascending or descending, drags it to a new priority (a handle at the start of the row; with two or more sorts) and removes it. Shown only when the table has at least two sortable columns. The limit is `multi_sort.max` (default 5).

How it works with Filament: the first sort is Filament's own (`tableSortColumn` / `tableSortDirection`, so the header arrow, the URL, `persistSortInSession` and the mobile sort select keep working); the others ride on it. Every sortable column gets a thin wrapper around its own `sortable(query: ...)`, so custom sort closures, relationship columns and `sortable([...])` keep working, and the sorts run in priority order inside Filament's own sort step (so grouping stays first and exports use the same order). The header cells of a multi-sort carry `data-sort-priority` and show a small number next to the label.

- A click on a column header keeps Filament's behaviour and **replaces** the multi-sort with that single sort (the stored list only counts while it still starts with Filament's current sort).
- Saved in views as `sorts` next to `sort` (see "Stored state"); presets use `defaultSort(['amount' => 'desc', 'ordered_at' => 'asc'])`.
- Adding the first sort to a table that has a string `defaultSort('amount', 'desc')` keeps that sort as #1 and appends the new one (a closure default has no column to extend, so the new sort becomes the first). Choosing the default column itself makes the default explicit.
- With `persistSortInSession()` the extra sorts are kept in the session next to Filament's own sort, and are restored only when they start with the restored sort.
- The Sort button is disabled, and the sort methods are no-ops, while records are being reordered (Filament ignores sorts then). When the maximum number of sorts is reached the dropdown says so.
- Reload: on a `ListRecords` page the extra sorts are mirrored in the URL as `?tableSorts=amount:desc,ordered_at:asc` (server-written, locked). On a fresh load they are validated (sortable columns, direction, count, no duplicates) and accepted only when they start with the sort the URL already carries. The Phase 2 "always show" rule applies, so a startup view's multi-sort survives a reload. Relation managers and widgets have no URL, so extra sorts are lost on a full reload there.
- Turn it off per table by overriding a static method on the component, or globally with `multiSort(false)` / `multi_sort.enabled`:

```php
public static function multiSortTableViews(): bool
{
    return false;
}
```

## Quick filters

Off by default (`quickFilters()` / `quick_filters.enabled`, or per table `quickFiltersTableViews()`). When on, **Filament's indicator bar is replaced** by a chip bar under the toolbar: it cannot be made clickable without forking Filament's view, so the plugin hides it and draws its own. The table's own `hiddenFilterIndicators()` call is ignored while quick filters are on (the two bars never show together). The bar keeps the search and column search chips exactly as Filament builds them, with the same remove buttons, plus one chip per filter that is **active** (it has an indicator, Filament counts it as active as `QueryBuilder` does, or its applied state holds a value, so a plain `form()` filter without `indicateUsing` is covered too) or **favorite**, in the order the table defines the filters, and a clear-all button.

- A filter chip opens a popover with that filter's own form group. The popover renders **only while its chip is open** (a click on the chip calls `openTableViewQuickFilter`, an outside click or Apply closes it), so a closed bar costs no option queries and a search keystroke or a sort adds nothing. The open chip's form is a clone of the filter's group in a form of its own, so it has its own state path (`tableViewQuickFilterState.<filter>.*`), DOM ids and wire keys: no id or `wire:key` is repeated with the filter dropdown, and a label click reaches the right field. Its changes are copied into `tableFilters` (or `tableDeferredFilters` when the table defers filters) and the popover is refilled from that state on every render, so it always matches Filament's filter dropdown. Changes apply at once; on a deferred table the popover has an **Apply** button (`applyTableFilters`). Only the open chip's own filter is copied; anything else the browser sends for that property is dropped.
- A filter with several fields (a date range) is **one** chip that edits all its fields; its label joins the indicators.
- The x on a chip calls Filament's own `removeTableFilter()`, so a favorite chip stays (inactive) and any other chip disappears. It respects each indicator's `removable()` flag: when every indicator is removable the whole filter is cleared; when only some are, the x removes just those (through their `removeField()`); when none is, there is no x.
- `->favorite()` keeps a chip visible while the filter is inactive. It is a macro on Filament's filter base class, so it works on every filter without swapping classes. It accepts a bool or a closure. `->quickFilterFavorite()` is the same call under the plugin's own name and is always registered. If another package already defines `BaseFilter::favorite()`, the plugin does **not** override it: it logs a warning at boot and `quickFilterFavorite()` is the way to mark a filter:

```php
SelectFilter::make('status')->options([...])->favorite(),
TernaryFilter::make('is_paid')->quickFilterFavorite(),
```

- The star in the popover pins or unpins a filter for the user. The choice is part of the view state (`favoriteFilters`, stored only when it differs from the `favorite()` defaults, so a later change in code still reaches users), kept in the session so a reload keeps it, reset by Default and by presets (`defaultFavoriteFilters([...])`).
- `limitIndicatorLabels(2)`: a multi-select chip shows "Status: A, B & 3 more". Only done when Filament's own label splits back into exactly the selected values, so it can never show something wrong.
- Chip popovers are filled from the filter state when they open, so a closed chip never loads options; a preloaded relationship select is queried once for the filter dropdown and once more only while its chip is open.

## Drag and drop

The view manager rows, the multi-sort list and the column list are reordered by dragging. It uses the `x-sortable` Alpine directive (SortableJS) that Filament already ships in its `support` script, so there is nothing to install, publish or load from a CDN.

- Each list is one `x-sortable` container whose rows carry `x-sortable-item="<key>"` and a handle (`x-sortable-handle`, an icon button with the `table-views::reorder.handle` alias). After a drop the browser sends the whole new order of that list to one server method: `reorderTableViews($type, $favorite, $keys)` (manager), `reorderTableViewSorts($columns)` (sort list), `reorderTableViewColumns($names)` (column list). The server accepts an order only when it is a list holding exactly the keys the user may reorder in that list, each once (no extra, missing, duplicate, unknown, hidden, other-section or other-user keys, no non-scalar values); anything else is ignored without an error.
- The manager reorders inside its own section only: favorites (presets and user views are two lists, presets first), my views, preset views. A list with one entry has no handle. Preset handles are not shown when `preset_views.manageable` is false.
- Keyboard: the handle is a focusable button; ArrowUp / ArrowDown on it moves the row one place through the same server method and keeps the focus on the handle. The handle's accessible name comes from `table-views::reorder.handle`.
- The dropdowns and the panel stay open after a drop: the lists are keyed Livewire elements, so a re-render keeps the sortable instance.
- `moveTableView`, `reorderTableViewSort` and `reorderTableViewColumn` (move one place up or down) still exist as server methods but no button calls them any more.

## Column reordering

The column toggle dropdown becomes a column list: per column a show/hide checkbox (toggleable columns only), a drag handle, and a **Reset order** link when the order is custom. Filament's trigger, width and placement are untouched; only the content is replaced. Tables with no toggleable column get a small **Columns** dropdown with the same list in the toolbar.

How it works: Filament rebuilds the table on every request and renders headers, cells and summaries from the table's column list, so the order is applied once per request on the freshly built table (the same `Column` objects pushed again in the saved order). Column actions, summaries, grouping, bulk selection and record URLs are untouched.

- The order is stored per user in the session like Filament's toggled columns, is part of the view state (`columnOrder`, stored only when it is not the code order) and of the "modified" check. Columns added in code later land at the end; removed columns are ignored. Presets: `defaultColumns([...])` (order + visibility) or `defaultToggledColumns([...])` (visibility only).
- Only tables with a flat list of columns are supported: column groups, layout components (Split, Stack, Panel) and collapsible layouts are left alone, as there is no clean meaning for "move up" inside them. The list follows the table's `columnToggleFormColumns()`. Columns the developer hides (`hidden()`) are not listed and keep their slot; columns hidden by the user's toggle are listed so they can be placed before they are shown again.
- Reordering is drag and drop (see "Drag and drop" above).
- Turn it off per table with `public static function columnOrderTableViews(): bool { return false; }`, or globally with `columnReordering(false)` / `column_reordering.enabled`.

## View manager

A button in the favorites bar (`view_manager.position`: `end` after Save view, or `start`) opens a Filament dropdown (fixed-positioned so the table card cannot clip it, with a 24rem max height and its own scroll), or a slide-over (`view_manager.slide_over`). Sections, empty ones hidden:

1. **Favorites**: favorite presets first, then favorite user views, each in the user's order.
2. **My views**: user views that are not favorites.
3. **Preset views**: visible presets that are not favorites.

Per row: apply (click), add/remove favorite, set/remove default (only with managed defaults), edit and delete (user views only), drag to reorder inside the section (a handle at the start of the row, shown when the section has two or more views; favorite presets and favorite user views are two separate lists, so presets stay before user views). A search box shows above 8 views (`view_manager.search` true/false overrides). Edit uses the same validation as Quick Save (name unique per user, table and tenant). Delete asks for confirmation; deleting the active view clears the active marker and leaves the table as it is.

The dropdown list loads on the first click (a Livewire call that unlocks it for that component), so a closed manager costs no queries. Every mutating call checks ownership on the server: view ids go through the scoped query, preset keys must be visible presets, and anything else is a silent no-op.

Storage: user views keep `is_favorite` and `sort` on `table_views`. Presets keep their choices in `table_view_preferences` (`is_favorite` null = the preset's own default, `sort`, `is_hidden`). `is_default` lives in `table_view_preferences` for both kinds. Reordering writes the whole order of that kind.

## Managed default view

With `managed_default_views` on, each user can mark one view per table (preset or own view) as their default in the manager. On a fresh load of the table, with no table state in the URL, the start view is picked in this order:

1. the view the user explicitly chose, remembered in the session (only with `persist_active_view_in_session`; includes an explicit press of Default, stored as `none`);
2. the user's managed default;
3. the developer default preset (`->default()`, or the preset keyed `default`), which applies even when managed defaults are off;
4. Filament's own defaults.

What the start step applies is not remembered as a choice: the session only holds views the user picked or saved. Choosing or removing a default in the manager is itself a new choice, so it clears the remembered entry and the new default applies on the next fresh load (the view on screen stays as it is).

A deleted view, or a preset that is hidden or removed, falls through silently. The Default button applies the developer default preset if there is one, otherwise it resets to Filament's defaults. Note: the default is applied on every fresh load, not only once per session; without session persistence nothing else would remember it. A start view overrides state that Filament itself restored from `persist*InSession`.

## Config

See `config/table-views.php`: table, preferences table and connection, `load_migrations`, user model, bar on/off and divider, `view_manager.*`, `preset_views.*`, `managed_default_views`, `multi_sort.*`, `quick_filters.*`, `column_reordering.enabled`, slide-over, max name length, colors, icons, `persist_active_view_in_session`, `state.tab` (also save the ListRecords `activeTab`, off by default) and `state.max_bytes`.

## Upgrading from Phase 1 (portal note)

Run the new migration (`table_view_preferences`) before deploying code that defines presets or turns on managed defaults. When the portal owns migrations, publish with `--tag=table-views-migrations` and copy the new file. It uses the same `table-views.connection` rule as `table_views`, so run it on the pinned connection (`php artisan migrate --database=<connection>`). It adds a unique index through raw SQL on Postgres and SQLite only.

Remap the new icon aliases to Tabler in the appearance config (see Icons). Components without presets behave as in Phase 1 and run no preferences query.

Phase 3 needs no migration. Multi-sort and column reordering are on for every component that uses the trait once the package is updated (the Sort button appears, the column toggle dropdown shows the column list); turn them off globally or per table if that is not wanted. Quick filters stay off until enabled. Views saved before Phase 3 load unchanged.

## Icons

Icons are never hard-coded. They are Filament icon aliases with heroicon defaults, registered only when nobody registered them first, so an app can remap them (for example to Tabler) with `FilamentIcon::register()`:

```
table-views::favorites-bar.default   table-views::quick-save
table-views::icons.star  heart  bookmark  flag  bolt  fire  funnel  clock  user  users
table-views::icons.tag  eye  chart-bar  currency  check-circle  warning  folder  inbox
table-views::icons.truck  calendar  shopping-cart  archive  document  bell  home  globe   (preset icons)
table-views::manager.open  preset  user-view  favorite  unfavorite  default  remove-default
table-views::manager.edit  delete  active
table-views::reorder.handle   (the drag handle of all three lists)
table-views::sort.open  asc  desc  remove
table-views::quick-filters.chevron  favorite  unfavorite  clear
table-views::columns.open  reset
```

The `icon` column stores the key (`star`, `chart-bar`, ...), resolved through the alias at render time.

## Translations

`en` and `ar` under the `table-views` namespace (`favorites-bar`, `quick-save`, `colors`, `icons`, `validation`, `manager`, `sort`, `quick-filters`, `columns`). Publish with `--tag=table-views-translations`.

## Stored state

```json
{"v":1,"filters":{},"search":"","columnSearches":{},"sort":{"column":null,"direction":null},"toggledColumns":{},"grouping":{"column":null,"direction":null}}
```

Empty maps are stored as `{}`. Readers ignore unknown keys and treat missing keys as empty.

Phase 3 adds three optional keys. They are written only when they differ from the default, so a view without them is byte-identical to a Phase 1 or 2 view, and `v` stays 1:

```json
{"sort":{"column":"amount","direction":"desc"},
 "sorts":[{"column":"amount","direction":"desc"},{"column":"ordered_at","direction":"asc"}],
 "columnOrder":["reference","status","amount"],
 "favoriteFilters":["status","is_paid"]}
```

`sort` is always the first entry of `sorts` (so a reader or a component without multi-sort still sees a valid single sort), and `sorts` appears only with two or more entries. `columnOrder` is the full order (absent = code order). `favoriteFilters` absent means the developer's `favorite()` filters, `[]` means none. Invalid entries are dropped when a view is applied.

## Limits

- The bar is rendered in the table header, so a table with no header content (no heading, search, filters, toggle or actions) shows no bar. On table widgets it sits below the heading; elsewhere above the header.
- Records per page and the `isTableReordering` flag are not saved. Applying a view turns reordering off.
- No UI to hide a preset (`is_hidden` is honored when set in the database).
- The Alpine behaviour (the dropdowns of the manager, the Sort button, the quick filter popovers and the column list, the header priority numbers) is covered by server-side tests only, not by a browser test. That includes opening a chip (the popover shows a spinner until the server renders its form), closing it on an outside click, and label clicks inside a popover.
- A quick filter popover is an extra round trip on open (the form is rendered lazily). An `afterStateUpdated` on a filter field runs on the popover's own copy of the form.
- A saved view never contains a preset's hidden query.
- The active view is restored on reload only where the table state is URL-bound (a `ListRecords` page). Relation managers, table widgets and custom Livewire classes without `#[Url]` table state have no URL for it, so a reload there starts from the usual start view (session, managed default, developer default).
- On a many-to-many relation manager, Filament resolves a row action's record (`getTableRecord`) straight from the relationship, not through the table query, so an active preset's hidden query does not limit which related record a row action can reach; the same holds for bulk actions when the relation allows duplicate pivot rows. Listing, counts, exports, summaries and other bulk actions do honor it. A plugin trait cannot scope that path without overriding Filament's own method (which collides with `InteractsWithTable`), and the hidden query is a filter, not access control anyway.
- Two tabs setting a default at the same instant can leave two default rows for a user (the earliest wins at startup); preference rows of presets removed from the code stay until the user's rows are deleted. Both are harmless and not cleaned up.
- Name uniqueness (per user, table and tenant, case-insensitive) is validated, not enforced by a database index. Case folding uses the database's `lower()` on both sides, so it follows the database collation.
- The bar's utility classes must be covered by the panel theme (the default Filament theme is enough).
- The Sort button, the quick filter chips and the column list live in the table header and toolbar, which Filament hides when the table has no search, filters, grouping or column toggle.
- Multi-sort and the column list change Filament's own state through its public API only (wrapped sort closures, `Table::columns()`, the cached `toggleTableColumnForm`), so they never collide with `InteractsWithTable`. A component that calls Filament's `resetTable()` rebuilds the table without them for the rest of that request.

## Tests

```bash
composer install
vendor/bin/pest
vendor/bin/pint
```
