# Colors and icons

Quick Save and the Edit view form use two grid pickers instead of selects. This page also explains how icons are resolved, how to use another icon set, and how translations work.

## The pickers

| Class | What it looks like |
| --- | --- |
| `TableKit\Forms\ColorGridPicker` | Round swatches. The selected one has a check and a ring. |
| `TableKit\Forms\IconGridPicker` | A scrollable grid of rendered icons. "No icon" comes first. It has a search box. |

- Both are radio groups. Arrow keys move and select (they follow the visual direction in RTL, and up/down move by row). Home and End jump. Enter and Space select.
- Every cell has a tooltip and an accessible label.
- The styles are inline in the field view (logical properties, light and dark), so they do not depend on the panel theme.
- They are normal Filament fields. You can use them in your own forms:

```php
use TableKit\Forms\ColorGridPicker;
use TableKit\Forms\IconGridPicker;

ColorGridPicker::make('color')->colors(['primary', 'success', 'rose']),
IconGridPicker::make('icon')->icons(['star', 'flag', 'truck']),
```

Validation accepts only the keys of the (filtered) lists on both Quick Save and Edit. Anything else is a normal field error.

## Colors

Set the list with `config('tablekit.colors')` or `->colors([...])` on the plugin.

The default list has 24 names, in this order:

| Group | Names |
| --- | --- |
| Filament's own | `primary` `gray` `success` `warning` `danger` `info` |
| Tailwind palettes | `red` `orange` `amber` `yellow` `lime` `green` `emerald` `teal` `cyan` `sky` `blue` `indigo` `violet` `purple` `fuchsia` `pink` `rose` `slate` |

- The plugin registers the extra palettes at the front of Filament's color list. A color that your app or a panel registers under the same name always wins, whether it registers before or after the plugin.
- The stored value is the name. A stored name that Filament does not know falls back to `primary`.
- A configured name that Filament has no color for is not offered and not accepted.
- If you register your own colors, add their names to the list. Give them a label in `tablekit::colors.<name>`. Without one, the name is capitalized.

## Icons

Set the list with `config('tablekit.icons')` or `->icons([...])` on the plugin.

- Icons are never hard-coded. Each key is a Filament icon alias, `tablekit::icons.<key>`, with a default icon.
- The defaults are registered only when nobody registered the alias first. So your app can remap any of them.
- The `icon` column stores the **key** (`star`, `chart-bar`, ...). It is resolved through the alias when the page renders.
- A key without an alias is not offered and not accepted.
- The search box matches the key, the label and the keywords, in the current language and in English. It filters in the browser.

The default list has about 120 keys, ordered by topic:

| Topic | Keys |
| --- | --- |
| Status and highlights | `star` `heart` `flag` `bookmark` `fire` `bolt` `sparkles` `trophy` `rocket` `light-bulb` `thumb-up` `thumb-down` `smile` `check-circle` `x-circle` `warning` `exclamation-circle` `info` `question` `no-symbol` `shield-check` `shield-exclamation` `lock` `unlock` |
| People | `user` `users` `user-circle` `user-group` `user-plus` `identification` `academic-cap` |
| Commerce and finance | `currency` `currency-dollar` `credit-card` `wallet` `receipt` `shopping-cart` `shopping-bag` `store` `gift` `ticket` `scale` `calculator` `briefcase` `bank` `cube` `truck` |
| Time | `clock` `calendar` `calendar-days` `bell` `bell-alert` `refresh` `play` |
| Documents | `document` `document-duplicate` `document-check` `document-search` `clipboard` `clipboard-list` `clipboard-check` `newspaper` `book` `paperclip` `pencil` `edit` `folder` `archive` `inbox` `printer` |
| Communication | `envelope` `chat` `chat-bubbles` `phone` `paper-airplane` `megaphone` `share` `link` |
| Places | `map-pin` `map` `globe` `globe-region` `building-office` `building-office-2` `home` |
| Charts and data | `chart-bar` `chart-pie` `chart-line` `trending-up` `trending-down` `table` |
| Organize | `tag` `funnel` `search` `eye` `eye-slash` `list` `queue` `squares` `stack` `adjustments` |
| Arrows | `arrow-up` `arrow-down` `arrow-left` `arrow-right` `swap` `undo` `download` `upload` |
| Tools and misc | `cog` `wrench` `key` `cloud` `code` `sun` `moon` `camera` `photo` `language` `fingerprint` `plus-circle` `minus-circle` `trash` |

### Add your own icon

1. Register the alias.
2. Add the key to the list (`icons` in config, or `->icons([...])`).
3. Optionally add a label in `tablekit::icons.<key>` and search words in `tablekit::icon-keywords.<key>` (separated by spaces).

```php
use Filament\Support\Facades\FilamentIcon;

FilamentIcon::register(['tablekit::icons.rocket-x' => 'heroicon-o-rocket-launch']);
```

## Icon aliases for the interface

The plugin's own buttons use aliases too. All of them have Heroicon defaults, and all can be remapped:

```text
tablekit::favorites-bar.default
tablekit::quick-save
tablekit::reorder.handle
tablekit::manager.open
tablekit::manager.preset
tablekit::manager.user-view
tablekit::manager.favorite
tablekit::manager.unfavorite
tablekit::manager.default
tablekit::manager.remove-default
tablekit::manager.edit
tablekit::manager.delete
tablekit::manager.active
tablekit::sort.open
tablekit::sort.asc
tablekit::sort.desc
tablekit::sort.remove
tablekit::quick-filters.chevron
tablekit::quick-filters.favorite
tablekit::quick-filters.unfavorite
tablekit::quick-filters.clear
tablekit::columns.open
tablekit::columns.reset
tablekit::icons.<key>        one alias per icon key above
```

`tablekit::reorder.handle` is the drag handle of all three lists (the view manager, the sort list and the column list).

### Use another icon set (for example Tabler)

Register your aliases in a service provider. Your registration wins over the plugin's defaults. You need a Blade icon set that provides the names you use.

```php
use Filament\Support\Facades\FilamentIcon;

public function boot(): void
{
    FilamentIcon::register([
        'tablekit::quick-save' => 'tabler-bookmark',
        'tablekit::favorites-bar.default' => 'tabler-refresh',
        'tablekit::manager.open' => 'tabler-list',
        'tablekit::reorder.handle' => 'tabler-grip-vertical',
        'tablekit::sort.open' => 'tabler-arrows-sort',
        'tablekit::icons.star' => 'tabler-star',
        'tablekit::icons.truck' => 'tabler-truck',
        // ...one line per alias you want to change
    ]);
}
```

## Translations

TableKit ships `en` and `ar` under the `tablekit` namespace:

`favorites-bar`, `quick-save`, `colors`, `icons`, `icon-keywords`, `pickers`, `validation`, `manager`, `reorder`, `sort`, `quick-filters`, `columns`.

Publish them to change a text or add a language:

```bash
php artisan vendor:publish --tag=tablekit-translations
```

The layout follows right-to-left languages.
