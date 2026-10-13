# elite/filament-table-views

Saved table views for Filament v5: save the current filters, search, column searches, sort, toggled columns and grouping as a named view, and reload it in one click from a favorites bar above the table.

This branch (`filament-v5`) is **Phase 1 only**: user views, Quick Save, favorites bar, Default reset, active/modified marker, optional remembered view. Preset views, the view manager, managed defaults, multi-sort, quick filters and column reordering from the Filament v3 plugin are not ported yet.

Requires PHP 8.2+, Laravel 11.28+ / 12 / 13, Filament 5.8.4+ (tested with 5.10.1) and Livewire 4 (tested with 4.4.7). Filament 5.0.0, 5.5.0, 5.7.0 and 5.8.0 were tried and fail in the package suite before reaching plugin code (a Livewire error-bag `TypeError`), so they are excluded. The Filament 4 / Livewire 3 version of this plugin lives on the `filament-v4` branch.

The plugin needs no Livewire 4 specific setup. The reload behavior relies on Livewire's `#[Url(keep: true)]` (`alwaysShow`), which Livewire 4 still has with the same browser rules.

## Install

```bash
composer require elite/filament-table-views
php artisan migrate
```

The `table_views` migration is loaded automatically. If your app owns its migrations, set `load_migrations` to `false` in the config and publish it instead:

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

## What a view holds

Filters, global search, column searches, sort, which toggleable columns are shown, grouping, and (with `state.tab`) the `ListRecords` tab.

Filament 4 and 5 keep column order and visibility in one state (the column manager). A view saves and applies **visibility only**: applying a view keeps the column order the user chose. The Default button resets both visibility and order, because that is Filament's own column state reset.

## Favorites bar and Quick Save

A bar in the table header: **Default** (reset to the table's own state), one button per favorite view in saved order, and **Save view** (Quick Save: name unique per user, table and tenant; color; optional icon; add to favorites). The active view is highlighted; once the table changes after applying it, it shows a modified mark.

On table widgets the bar sits below the heading; elsewhere above the header content.

## Remembered view and reload

With `persistActiveViewInSession` the view the user last applied or saved is applied again on the next fresh load of that table. It is not applied when the URL already carries table state (`filters`, `search`, `sort`, `grouping`, `tab`, `reordering`: the Filament 4 and 5 names are read from the component's `#[Url]` attributes). A deleted view is forgotten.

When the remembered view is applied on load, the plugin asks the browser to always write the table state that the view changed, so a reload shows the same screen even after edits that land back on the view's values or clear them (Livewire drops a URL parameter equal to its value after mount). What Filament itself persisted in the session (column visibility when it differs from the default, the sort with `persistSortInSession`) is put back on top of the view. The active marker is not part of the URL: a reload with table state in the URL shows that state without a highlighted view.

## Config

See `config/table-views.php`: table and connection, `load_migrations`, user model, bar on/off, slide-over, max name length, colors, icons, `persist_active_view_in_session`, `state.tab` (also save the ListRecords `activeTab`, off by default) and `state.max_bytes`.

## Icons

Icons are never hard-coded. They are Filament icon aliases with `Heroicon` defaults, registered only when nobody registered them first, so an app can remap them (for example to Tabler) with `FilamentIcon::register()`:

```
table-views::favorites-bar.default   table-views::quick-save
table-views::icons.star  heart  bookmark  flag  bolt  fire  funnel  clock  user  users
table-views::icons.tag  eye  chart-bar  currency  check-circle  warning  folder  inbox
```

The `icon` column stores the key (`star`, `chart-bar`, ...), resolved through the alias at render time.

## Translations

`en` and `ar` under the `table-views` namespace (`favorites-bar`, `quick-save`, `colors`, `icons`, `validation`). Publish with `--tag=table-views-translations`.

## Stored state

```json
{"v":1,"filters":{},"search":"","columnSearches":{},"sort":{"column":null,"direction":null},"toggledColumns":{},"grouping":{"column":null,"direction":null}}
```

Empty maps are stored as `{}`. Readers ignore unknown keys and treat missing keys as empty. The shape is the same as the Filament v3 plugin's, so views saved there load here: a nested `toggledColumns` map (v3 stored dotted column names as nested arrays) is flattened, and sort and grouping are converted to Filament 4 and 5's `column:direction` properties on apply.

## Limits

- The bar is rendered in the table header, so a table with no header content (no heading, search, filters, grouping, column manager or actions) shows no bar.
- Records per page and the `isTableReordering` flag are not saved. Applying a view turns reordering off.
- A column added after a view was saved is shown or hidden by its own default. Names that are no longer toggleable columns are dropped.
- The remembered view is restored on reload only where the table state is URL-bound (a `ListRecords` page). Elsewhere (relation managers, table widgets) there is no URL, so the session alone decides.
- Name uniqueness (per user, table and tenant, case-insensitive) is validated, not enforced by a database index. Case folding uses the database's `lower()` on both sides, so it follows the database collation.
- The bar's utility classes must be covered by the panel theme (the default Filament theme is enough).

## Tests

```bash
composer install
vendor/bin/pest
vendor/bin/pint --test
```
