# elite/filament-table-views

Saved table views for Filament v4: save the current filters, search, column searches, sort, toggled columns and grouping as a named view, and reload it in one click from a favorites bar above the table.

This branch (`filament-v4`) covers **Phases 1 and 2**: user views, Quick Save, favorites bar, Default reset, active/modified marker, optional remembered view, plus preset views, the view manager (dropdown or slide-over, drag and drop reorder), managed default views and the `?view=` URL marker. Multi-sort, quick filters and plugin-owned column order (Phase 3 on Filament v3) are not ported: Filament 4 has its own column manager with reordering.

Requires PHP 8.2+, Laravel 11.28+ / 12 / 13, Filament 4 (tested with 4.15, Livewire 3.8).

## Install

```bash
composer require elite/filament-table-views
php artisan migrate
```

The `table_views` and `table_view_preferences` migrations are loaded automatically. If your app owns its migrations, set `load_migrations` to `false` in the config and publish it instead:

```bash
php artisan vendor:publish --tag=table-views-migrations   # both migration files
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

Every option is optional and falls back to `config/table-views.php`. The favorites bar also works without registering the plugin (config only). The table name is config only (`table-views.table`) because the migration reads it too.

## User key

`user_id` is a string column (64 chars), so integer, ULID and UUID user keys all work with no setting. Views are scoped by the auth identifier (`Filament::auth()->id()`) compared as a string. There is no foreign key to the users table.

## Database connection

`connection` is `null` by default, which means the app's default connection **at the moment of use**. If your app changes `database.default` per panel or module, views would then be read from a different database than the one you migrated. In that case pin the connection, either in config or per panel (the plugin value wins):

```php
// config/table-views.php
'connection' => 'kyc',

// or per panel
TableViewsPlugin::make()->connection('kyc')
```

The migration uses `config('table-views.connection')`, or the default connection when it is `null`, so for a per-panel connection run it against that connection:

```bash
php artisan migrate --database=kyc
```

Each connection that serves a panel with the plugin needs its own `table_views` table.

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
            ->defaultFilters(['status' => ['value' => 'shipped']])   // raw `filters` state, per filter name
            ->defaultSort('ordered_at', 'desc')                      // an array of pairs is accepted: only the first is used
            ->defaultSearch('foo')
            ->defaultColumnSearches(['reference' => 'ORD'])
            ->defaultToggledColumns(['notes', 'customer.city'])      // the toggleable columns that are shown; the others are hidden
            ->defaultGrouping('status', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->where(...))  // hidden query, only while this preset is active
            ->badge(fn () => Order::where(...)->count())->badgeColor('warning')->cacheBadgeFor(60)   // cacheBadgeFor: optional, in seconds
            ->tooltip('Orders on the way')
            ->visible(fn () => auth()->user()->can('...'))           // and ->hidden()
            ->default()                                              // or ->default(fn () => bool)
            ->preserveAll(),                   // or preserveFilters() / preserveToggledColumns() / preserveSort() / preserveSearch()
    ];
}
```

- Keys are 1 to 100 letters, digits, `_ . : -`. A wrong value or key throws.
- Applying a preset resets every part to the table defaults, then applies the parts the preset defines. Parts it keeps with `preserve*` are left as they are. A preset that defines nothing is a plain reset.
- Filament 4 keeps column order and visibility in one state (the column manager). A preset changes **visibility only** and keeps the column order the user chose. The Default button without a developer default preset is Filament's own reset and also resets the order.
- Filters are laid over the table's filter defaults, per filter name. `defaultFilters([])` therefore keeps the defaults; name a filter to change it.
- `modifyQueryUsing` is active only while the preset is the active view. It is kept in locked server state (`activeTablePresetKey`), so it survives Livewire requests, the browser cannot set it, and it ends when another view or Default is applied. It also applies to the filtered query, exports and summaries. It stops applying if the preset is hidden or removed. It is added through `Table::configureUsing()`, so no change to the component's `table()` is needed.
- The `modifyQueryUsing` closure runs inside a nested where group on a fresh builder of the table's model, so `orWhere` inside it can never escape the table's own constraints (tenant scope, relation manager parent, Filament filters and search). Scopes the closure removes (`withoutGlobalScopes()`, `withoutGlobalScope()`, `withTrashed()`, `onlyTrashed()`) are not carried over to the table query, so a preset can never drop the tenant scope or a soft-delete scope; use a Filament filter such as `TrashedFilter` for trashed rows. Only where constraints are carried over (model scopes and `whereHas` work); ordering belongs in `defaultSort()`, joins and eager loads in the table query. The closure also runs when Filament resolves a record for a row action (it receives the same query scopes with `isResolvingRecord`), and it cannot opt out: a two-step action that moves a record out of the active preset fails on its second step. The closure runs every time the table query is built, several times per request: keep it cheap and free of side effects.
- **A hidden preset query is a filter, not an access-control boundary.** The user can drop it at any time (apply their own view, press Default, pick another preset), so never use it to hide data they must not see. Put authorization in the resource query, policies or global scopes.
- **Reload**: on a `ListRecords` / `ManageRelatedRecords` page the active view is mirrored in the URL as `?view=preset:<key>` or `?view=user:<id>` (server-written, locked against Livewire updates). On a fresh load it is read back through the same checks as applying (visible presets only, the user's own views only, anything else is ignored and the normal start view applies). The view is applied, then the filters, sort, search and so on read from the URL (`filters`, `search`, `sort`, `grouping`, `tab`) are put back on top, so the URL state wins and only the active marker and a preset's hidden query return. A link with `view` but no other table state also restores the view, and it beats the startup default. Absent table parameters mean the table's own defaults, and an empty `?view=` means "no view, do not apply the startup view" (written after Default is clicked). When a view is applied on the first load (startup default, remembered view), the plugin asks the browser to always write the table state that view set, so a reload shows the same screen even after edits that land back on the view's values or clear them. Column visibility the user changed after the view and Filament kept in the session is put back on top of the view; when it equals the table defaults it is indistinguishable from "never touched" and the view's own columns apply.
- Quick Save while such a preset is active shows a notification instead (the hidden query could not be part of the saved view). Set `preset_views.create_from_preset_with_query` to allow it: the saved view then holds the visible state only.
- The preset keyed `default` replaces the built-in Default button (label, icon, color, tooltip, badge) and is the implicit developer default.
- `badge()`, `badgeColor()` and `tooltip()` closures run at most once per request, only for presets shown in the bar and only when the bar renders. Every Livewire request re-renders the bar (search, sort, pagination, polling), so a badge that queries still runs on each of them: add `->cacheBadgeFor($seconds)` to keep the value in the app cache per user, tenant and table.
- Custom icon keys: register `FilamentIcon::register(['table-views::icons.rocket' => '...'])`. An unknown key shows no icon.
- Presets of a component are ordered as returned, unless the user reordered them in the manager.

## View manager

A button in the favorites bar (`view_manager.position`: `end` after Save view, or `start`) opens a Filament dropdown or a slide-over (`view_manager.slide_over`). The dropdown is a plain popover (`menu: false`), fixed-positioned (`teleport`) so the table card cannot clip it, with a 24rem max height and its own scroll. Sections, empty ones hidden:

1. **Favorites**: favorite presets first, then favorite user views, each in the user's order.
2. **My views**: user views that are not favorites.
3. **Preset views**: visible presets that are not favorites.

Per row: apply (click), add/remove favorite, set/remove default (only with managed defaults), edit and delete (user views only), drag to reorder inside the section (a handle at the start of the row, shown when the section has two or more views; favorite presets and favorite user views are two separate lists, so presets stay before user views). A search box shows above 8 views (`view_manager.search` true/false overrides). Edit uses the same validation as Quick Save (name unique per user, table and tenant). Delete asks for confirmation; deleting the active view clears the active marker and leaves the table as it is.

The dropdown list loads when the panel first becomes visible (`x-intersect` on its loading line, a Livewire call that unlocks it for that component), so it works for mouse, touch and keyboard alike and a closed manager costs no queries. Every mutating call checks ownership on the server: view ids go through the scoped query, preset keys must be visible presets, and anything else is a silent no-op. The `viewId` of the edit and delete actions is client-writable action state, so both actions resolve the view again through the scoped query on mount and on submit.

In slide-over mode Filament renders only the open modal while an action is mounted. A change made there (favorite, default, order, edit, delete) forces a full render so the favorites bar behind it stays current. Edit and delete are registered as modal actions of the slide-over (`registerModalActions`), which is how Filament 4 resolves a nested mounted action.

Storage: user views keep `is_favorite` and `sort` on `table_views`. Presets keep their choices in `table_view_preferences` (`is_favorite` null = the preset's own default, `sort`, `is_hidden`). `is_default` lives in `table_view_preferences` for both kinds. Reordering writes the whole order of that kind.

### Drag and drop

The manager rows are reordered by dragging. It uses the `x-sortable` Alpine directive (SortableJS) that Filament 4 ships in its `support` script, so there is nothing to install, publish or load from a CDN.

- Each list is one `x-sortable` container whose rows carry `x-sortable-item="<key>"` and a handle (`x-sortable-handle`, an icon button with the `table-views::reorder.handle` alias). After a drop (Filament's `end` event) the browser sends the whole new order of that list to `reorderTableViews($type, $favorite, $keys)`. The server accepts an order only when it is a list holding exactly the keys the user may reorder in that list, each once (no extra, missing, duplicate, unknown, hidden, other-section or other-user keys, no non-scalar values); anything else is ignored without an error.
- Keyboard: the handle is a focusable button; ArrowUp / ArrowDown on it moves the row one place through the same server method and keeps the focus on the handle. The handle's accessible name comes from `table-views::reorder.handle`.
- `moveTableView` (move one place up or down) still exists as a server method but no button calls it.

## Managed default view

With `managed_default_views` on, each user can mark one view per table (preset or own view) as their default in the manager. On a fresh load of the table, with no table state in the URL, the start view is picked in this order:

1. the view the user explicitly chose, remembered in the session (only with `persist_active_view_in_session`; includes an explicit press of Default, stored as `none`);
2. the user's managed default;
3. the developer default preset (`->default()`, or the preset keyed `default`), which applies even when managed defaults are off;
4. Filament's own defaults.

What the start step applies is not remembered as a choice: the session only holds views the user picked or saved. Choosing or removing a default in the manager is itself a new choice, so it clears the remembered entry and the new default applies on the next fresh load (the view on screen stays as it is).

A deleted view, or a preset that is hidden or removed, falls through silently. The Default button applies the developer default preset if there is one, otherwise it resets to Filament's defaults. Note: the default is applied on every fresh load, not only once per session; without session persistence nothing else would remember it. A start view overrides state that Filament itself restored from `persist*InSession` (filters, search), except column visibility and the sort with `persistSortInSession`, which are put back when the user changed them. A column list or sort that a view apply left in the session (the plugin records what it wrote, per table) is not a user change, so a changed default brings its own columns and sort on the next visit. Column toggles are compared as a map, so moving a column does not count as a change.

## Styling

Filament 4's precompiled CSS has no Tailwind utilities of its own, so the plugin views use inline styles for layout and only Filament's `fi-*` classes (`fi-sr-only`, `fi-dropdown-*`). Colors come from the CSS variables Filament defines (`var(--primary-500)`, `var(--gray-500)`). A test forbids other utility classes in the views.

## What a view holds

Filters, global search, column searches, sort, which toggleable columns are shown, grouping, and (with `state.tab`) the `ListRecords` tab.

Filament 4 keeps column order and visibility in one state (the column manager). A view saves and applies **visibility only**: applying a view keeps the column order the user chose. The Default button resets both visibility and order, because that is Filament's own column state reset.

## Favorites bar and Quick Save

A bar in the table header: **Default** (reset to the table's own state), one button per favorite view in saved order, and **Save view** (Quick Save: name unique per user, table and tenant; color; optional icon; add to favorites). The active view is highlighted; once the table changes after applying it, it shows a modified mark.

On table widgets the bar sits below the heading; elsewhere above the header content.

## Remembered view and reload

(Preset views and the `?view=` marker, above, extend this: a reload of a URL-bound table brings back the active view and a preset's hidden query.)

With `persistActiveViewInSession` the view the user last applied or saved is applied again on the next fresh load of that table. It is not applied when the URL already carries table state (`filters`, `search`, `sort`, `grouping`, `tab`, `reordering`: the Filament 4 names are read from the component's `#[Url]` attributes). A deleted view is forgotten.

When the remembered view is applied on load, the plugin asks the browser to always write the table state that the view changed, so a reload shows the same screen even after edits that land back on the view's values or clear them (Livewire drops a URL parameter equal to its value after mount). What Filament itself persisted in the session (column visibility when it differs from the default, the sort with `persistSortInSession`) is put back on top of the view. The active view is mirrored in the URL as `?view=` (see Preset views, Reload).

## Config

See `config/table-views.php`: table, preferences table and connection, `load_migrations`, user model, bar on/off and divider, `view_manager.*`, `preset_views.*`, `managed_default_views`, quick save slide-over, max name length, colors, icons, `persist_active_view_in_session`, `state.tab` (also save the ListRecords `activeTab`, off by default) and `state.max_bytes`.

## Upgrading from Phase 1

Run the new migration (`table_view_preferences`) before deploying code that defines presets or turns on managed defaults. It uses the same `table-views.connection` rule as `table_views` (`php artisan migrate --database=<connection>` for a pinned connection) and adds a unique index through raw SQL on Postgres and SQLite only. Components without presets behave as in Phase 1 and run no preferences query. Remap the new icon aliases (see Icons) if you use another icon set.

## Icons

Icons are never hard-coded. They are Filament icon aliases with `Heroicon` defaults, registered only when nobody registered them first, so an app can remap them (for example to Tabler) with `FilamentIcon::register()`:

```
table-views::favorites-bar.default   table-views::quick-save   table-views::reorder.handle
table-views::manager.open  preset  user-view  favorite  unfavorite  default  remove-default  edit  delete  active
table-views::icons.star  heart  bookmark  flag  bolt  fire  funnel  clock  user  users
table-views::icons.tag  eye  chart-bar  currency  check-circle  warning  folder  inbox
table-views::icons.truck  calendar  shopping-cart  archive  document  bell  home  globe   (preset icon keys)
```

The `icon` column stores the key (`star`, `chart-bar`, ...), resolved through the alias at render time.

## Translations

`en` and `ar` under the `table-views` namespace (`favorites-bar`, `quick-save`, `colors`, `icons`, `validation`, `manager`, `reorder`). Publish with `--tag=table-views-translations`.

## Stored state

```json
{"v":1,"filters":{},"search":"","columnSearches":{},"sort":{"column":null,"direction":null},"toggledColumns":{},"grouping":{"column":null,"direction":null}}
```

Empty maps are stored as `{}`. Readers ignore unknown keys and treat missing keys as empty. The shape is the same as the Filament v3 plugin's, so views saved there load here: a nested `toggledColumns` map (v3 stored dotted column names as nested arrays) is flattened, and sort and grouping are converted to Filament 4's `column:direction` properties on apply.

## Limits

- The bar is rendered in the table header, so a table with no header content (no heading, search, filters, grouping, column manager or actions) shows no bar.
- Records per page and the `isTableReordering` flag are not saved. Applying a view turns reordering off.
- A column added after a view was saved is shown or hidden by its own default. Names that are no longer toggleable columns are dropped.
- The remembered view is restored on reload only where the table state is URL-bound (a `ListRecords` page). Elsewhere (relation managers, table widgets) there is no URL, so the session alone decides.
- Name uniqueness (per user, table and tenant, case-insensitive) is validated, not enforced by a database index. Case folding uses the database's `lower()` on both sides, so it follows the database collation.
- The Alpine behavior (the dropdown, drag and drop, keyboard reorder, the slide-over) is covered by server-side tests; it was exercised once by hand in a browser (dropdown, apply, keyboard and mouse reorder) but there is no browser test.
- A saved view never contains a preset's hidden query.
- On Filament 4 the hidden query also applies when a row action resolves its record, including on a many-to-many relation manager (Filament passes the table query scopes with `isResolvingRecord`). A preset closure cannot opt out of that. The hidden query is a filter, not access control.
- The `?view=` marker exists only where the table state is URL-bound (`ListRecords`, `ManageRelatedRecords`). Relation managers and table widgets have no URL for it, so a reload there starts from the usual start view.
- Two tabs setting a default at the same instant can leave two default rows for a user (the earliest wins at startup); preference rows of presets removed from the code stay until the user's rows are deleted. Both are harmless and not cleaned up.
- No UI to hide a preset (`is_hidden` is honored when set in the database).
- Column visibility that the user set back to the table defaults, or to exactly what the last applied view wrote, is not told apart from "never changed", so the start view's own columns apply on the next load.

## Tests

```bash
composer install
vendor/bin/pest
vendor/bin/pint --test
```
