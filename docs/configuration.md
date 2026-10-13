# Configuration

You can configure TableKit in two places:

- the config file, `config/tablekit.php`, for app-wide defaults;
- the plugin, `TableKitPlugin::make()`, per panel.

The plugin wins over the config file. Every option is optional. The favorites bar also works without the plugin (config only).

Publish the config file if you want to change it:

```bash
php artisan vendor:publish --tag=tablekit-config
```

## Plugin options

All options are optional. Options marked `bool|Closure` also accept a closure that returns a boolean.

```php
use TableKit\TableKitPlugin;

$panel->plugin(
    TableKitPlugin::make()
        ->favoritesBar()
        ->quickSaveSlideOver()
        ->colors(['primary', 'success', 'rose'])
        ->icons(['star', 'flag'])
        ->userModel(App\Models\User::class)
        ->persistActiveViewInSession()
        ->connection('kyc')
        ->maxViewsPerTable(100)
        ->viewManager(enabled: true, slideOver: false, position: 'end')
        ->presetViewsManageable(true)
        ->newPresetViewSortPosition('after')
        ->presetViewLockIcon(true)
        ->favoritesBarDivider(true)
        ->managedDefaultViews(false)
        ->createFromPresetWithQuery(false)
        ->multiSort(true, max: 5)
        ->quickFilters(false)
        ->limitIndicatorLabels(2)
        ->columnReordering(true)
);
```

| Method | Config key | Default | What it does |
| --- | --- | --- | --- |
| `favoritesBar(bool\|Closure)` | `favorites_bar.enabled` | `true` | Shows the bar above the table. |
| `favoritesBarDivider(bool\|Closure)` | `favorites_bar.divider` | `true` | Draws a divider between preset views and the user's own views. |
| `quickSaveSlideOver(bool\|Closure)` | `quick_save.slide_over` | `false` | Opens Quick Save in a slide-over instead of a modal. |
| `viewManager(enabled, slideOver, position)` | `view_manager.enabled`, `view_manager.slide_over`, `view_manager.position` | `true`, `false`, `'end'` | Turns the view manager on, opens it as a slide-over, and places its button at `'start'` or `'end'`. |
| `presetViewsManageable(bool\|Closure)` | `preset_views.manageable` | `true` | `false` means users cannot reorder or unfavorite presets. |
| `newPresetViewSortPosition('before'\|'after')` | `preset_views.new_sort_position` | `'after'` | Where presets the user has not ranked yet go. |
| `presetViewLockIcon(bool\|Closure)` | `preset_views.lock_icon` | `true` | Shows a lock icon on preset rows in the manager. |
| `createFromPresetWithQuery(bool\|Closure)` | `preset_views.create_from_preset_with_query` | `false` | Allows Quick Save while a preset with a hidden query is active. The hidden query is not saved. |
| `managedDefaultViews(bool\|Closure)` | `managed_default_views` | `false` | Lets each user mark one view per table as their default. |
| `multiSort(enabled, max)` | `multi_sort.enabled`, `multi_sort.max` | `true`, `5` | Turns multi-sort on and sets the most sort columns. `max` is kept between 2 and 20. |
| `quickFilters(bool\|Closure)` | `quick_filters.enabled` | `false` | Replaces Filament's indicator bar with clickable filter chips. |
| `limitIndicatorLabels(?int)` | `quick_filters.limit_labels` | `2` | A multi-select chip shows this many values, then "& N more". `0` or `null` shows all. |
| `columnReordering(bool\|Closure)` | `column_reordering.enabled` | `true` | Saves and applies column order in views and presets. |
| `maxViewsPerTable(int)` | `max_views_per_table` | `100` | Most views one user can save per table and tenant. |
| `colors(array)` | `colors` | 24 names | Color names offered in the color picker. |
| `icons(array)` | `icons` | about 120 keys | Icon keys offered in the icon picker. |
| `userModel(string)` | `user.model` | `null` | User model class. Only used by the `TableView::user()` relation. Falls back to the model of your default auth guard. |
| `persistActiveViewInSession(bool)` | `persist_active_view_in_session` | `false` | Applies the user's last view again on the next fresh load. |
| `connection(?string)` | `connection` | `null` | Database connection that holds the TableKit tables. |

## Config file

These keys are in `config/tablekit.php`.

| Key | Default | What it does |
| --- | --- | --- |
| `table` | `'tablekit_views'` | Name of the views table. Config only, because the migration reads it. |
| `preferences_table` | `'tablekit_preferences'` | Name of the per-user preferences table. Config only, because the migration reads it. |
| `connection` | `null` | `null` means the app's default connection at the moment of use. See [Database](database.md#database-connection). |
| `load_migrations` | `true` | Set to `false` if your app owns the migrations. See [Database](database.md#publish-the-migrations). |
| `user.model` | `null` | User model class. Only used by the `TableView::user()` relation. |
| `favorites_bar.enabled` | `true` | Shows the favorites bar. |
| `favorites_bar.divider` | `true` | Divider between preset views and the user's own views. |
| `view_manager.enabled` | `true` | Shows the view manager button. |
| `view_manager.slide_over` | `false` | Opens the manager as a slide-over instead of a dropdown. |
| `view_manager.position` | `'end'` | `'start'` puts the button before Default. `'end'` puts it after Save view. |
| `view_manager.search` | `null` | `null` shows the search box when there are more than 8 views. `true` or `false` forces it. Config only. |
| `preset_views.manageable` | `true` | `false` means users cannot reorder or unfavorite presets. |
| `preset_views.new_sort_position` | `'after'` | `'before'` or `'after'` the ranked presets. |
| `preset_views.lock_icon` | `true` | Lock icon on preset rows in the manager. |
| `preset_views.create_from_preset_with_query` | `false` | Allow Quick Save while a preset with a hidden query is active. |
| `managed_default_views` | `false` | Let each user mark one view per table as their default. |
| `multi_sort.enabled` | `true` | Turns multi-sort on. |
| `multi_sort.max` | `5` | Most sort columns. Kept between 2 and 20. |
| `quick_filters.enabled` | `false` | Turns quick filters on. |
| `quick_filters.limit_labels` | `2` | Values a multi-select chip shows before "& N more". `0` shows all. |
| `column_reordering.enabled` | `true` | Saves and applies column order in views and presets. |
| `quick_save.slide_over` | `false` | Opens Quick Save in a slide-over. |
| `quick_save.max_name_length` | `100` | Longest view name. Kept between 1 and 100. Config only. |
| `max_views_per_table` | `100` | Most views one user can save per table and tenant. |
| `colors` | 24 names | Colors in the picker, in this order. See [Colors and icons](colors-and-icons.md). |
| `icons` | about 120 keys | Icons in the picker, in this order. See [Colors and icons](colors-and-icons.md). |
| `persist_active_view_in_session` | `false` | Applies the user's last view again on the next fresh load. |
| `state.tab` | `false` | Also saves and applies the list page's active tab. Config only. |
| `state.max_bytes` | `65536` | Most bytes one saved view can hold. Quick Save refuses a larger view with a name field error. Config only. |

## Turn features off for one table

Override a static method on the page, relation manager or widget that uses the trait:

```php
public static function multiSortTableViews(): bool
{
    return false;
}

public static function quickFiltersTableViews(): bool
{
    return true;
}

public static function columnOrderTableViews(): bool
{
    return false;
}
```

| Method | What it controls |
| --- | --- |
| `multiSortTableViews()` | The Sort button. Default: the global setting. |
| `quickFiltersTableViews()` | The quick filter chips. Default: the global setting. |
| `columnOrderTableViews()` | Saving and applying column order. Default: the global setting. |

## Other hooks on the component

| Method | What it does |
| --- | --- |
| `getPresetTableViews()` | Returns the preset views. Declare it `protected`. See [Presets](presets.md). |
| `getTableViewsResourceKey()` | The key views are stored under. The default is the component class. Override it (protected) to share views between two classes. |

## Table-wide notes

- Views are stored per user, per tenant (`Filament::getTenant()`) and per component class.
- Declare `InteractsWithTable` before `HasTableKit` in a custom Livewire class. `ListRecords`, relation managers and `TableWidget` already include it. The remembered-view restore is skipped when the table is not ready yet.
