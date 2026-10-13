# Preset views

A preset is a view that you define in code. Users can apply, favorite and reorder presets, but not edit or delete them. A preset never touches the database.

Return presets from the component that uses `HasTableKit`. Declare the method `protected`. A `public` method can be called from the browser. It would only return the presets, but there is no reason to expose it.

```php
use Illuminate\Database\Eloquent\Builder;
use TableKit\Presets\PresetTableView;

protected function getPresetTableViews(): array
{
    return [
        'shipped' => PresetTableView::make()
            ->label(__('Shipped'))
            ->icon('truck')
            ->color('success')
            ->favorite()
            ->defaultFilters(['status' => ['value' => 'shipped']])
            ->defaultSort('ordered_at', 'desc')
            ->defaultSearch('foo')
            ->defaultColumnSearches(['reference' => 'ORD'])
            ->defaultToggledColumns(['notes', 'customer.city'])
            ->defaultGrouping('status', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('is_paid', true))
            ->badge(fn () => Order::where('status', 'shipped')->count())
            ->badgeColor('warning')
            ->cacheBadgeFor(60)
            ->tooltip('Orders on the way')
            ->visible(fn () => auth()->user()->can('view-shipped'))
            ->preserveSearch(),
    ];
}
```

The array key is the preset key. Keys are 1 to 100 letters, digits, `_`, `.`, `:` or `-`. A wrong key or value throws an error. Presets show in the order you return them, unless the user reordered them in the [manager](views.md#view-manager).

## Methods

Every method returns the preset, so you can chain them.

| Method | What it does |
| --- | --- |
| `make()` | Creates a preset. |
| `label(string\|Closure\|null)` | The button text. Without it, the key is turned into a headline. |
| `icon(?string)` | An icon **key** such as `truck`. It is resolved through the `tablekit::icons.<key>` alias. See [Colors and icons](colors-and-icons.md). |
| `color(?string)` | A color name, such as `success`. |
| `favorite(bool\|Closure)` | Shows the preset in the favorites bar, unless the user unfavorites it. |
| `defaultFilters(?array)` | Raw `filters` state, per filter name. Laid over the table's filter defaults. |
| `defaultSort(string\|array\|null, string $direction = 'asc')` | One sort (`'ordered_at', 'desc'`) or several (`['amount' => 'desc', 'ordered_at' => 'asc']`). The first entry has the highest priority. |
| `defaultSearch(?string)` | The global search text. |
| `defaultColumnSearches(?array)` | Column search values, per column name. |
| `defaultToggledColumns(?array)` | The toggleable columns that are shown. The others are hidden. It changes visibility only and keeps the user's column order. |
| `defaultColumns(?array)` | Column order and visibility in one call. See [Columns](columns.md). |
| `defaultFavoriteFilters(?array)` | Filter names pinned as chips in the quick filter bar. Replaces the filters marked with `favorite()`. See [Quick filters](quick-filters.md). |
| `defaultGrouping(?string, string $direction = 'asc')` | The grouping column and direction. |
| `modifyQueryUsing(?Closure)` | A hidden query, active only while this preset is the active view. See [Hidden query](#hidden-query). |
| `badge(mixed)` | A badge value or a closure that returns one. Only a plain text or number value is shown. |
| `badgeColor(string\|Closure\|null)` | The badge color. The default is `primary`. |
| `cacheBadgeFor(?int $seconds)` | Keeps the badge value in the app cache, per user, tenant and table. |
| `tooltip(string\|Closure\|null)` | The button tooltip. |
| `visible(bool\|Closure)` | Shows the preset when the condition is true. |
| `hidden(bool\|Closure)` | Hides the preset when the condition is true. |
| `default(bool\|Closure)` | Makes this the developer default preset. |
| `preserveAll(bool)` | Keeps every part as it is when the preset is applied. |
| `preserveFilters(bool)` | Keeps the filters. This also keeps the pinned filters. |
| `preserveToggledColumns(bool)` | Keeps the column visibility. This also keeps the column order. |
| `preserveSort(bool)` | Keeps the sort. |
| `preserveSearch(bool)` | Keeps the search and the column searches. |
| `key(string)` | Sets the key. Set for you from the array key. |
| `livewire(mixed)` | Passes the Livewire component to the hidden query. Set for you. |
| `cacheScope(string)` | Sets the table key for the badge cache, so tables never share a cached badge. Set for you. |

## What happens when a preset is applied

- Every part is first reset to the table defaults. Then the preset applies the parts it defines. Parts you keep with `preserve*` are left as they are.
- A preset that defines nothing is a plain reset.
- Filters are laid over the table's filter defaults, per filter name. `defaultFilters([])` therefore keeps the defaults. Name a filter to change it.
- The Default button puts the column order back to the code order, with or without a developer default preset.

## Default preset

- Use `->default()` on one preset, or name a preset `default`.
- It applies at start even when `managed_default_views` is off (see [start view order](views.md#default-views-and-the-start-view)).
- The preset keyed `default` also replaces the built-in Default button: its label, icon, color, tooltip and badge.

## Hidden query

`modifyQueryUsing` filters the table while the preset is the active view.

```php
PresetTableView::make()
    ->label('My open orders')
    ->modifyQueryUsing(fn (Builder $query) => $query
        ->where('assigned_to', auth()->id())
        ->where('status', 'open'));
```

How it works:

- **It is a filter, not access control.** The user can drop it at any time: apply their own view, press Default, or pick another preset. Never use it to hide data they must not see. Put authorization in the resource query, policies or global scopes.
- It is kept in locked server state (`activeTablePresetKey`). It survives Livewire requests, the browser cannot set it, and it ends when another view or Default is applied. It stops applying if the preset is hidden or removed.
- It applies to the filtered query, to exports and to summaries.
- It is added through `Table::configureUsing()`, so you do not change the component's `table()` method.
- The closure runs inside a nested where group on a fresh builder of the table's model. So `orWhere` inside it can never escape the table's own constraints (tenant scope, relation manager parent, Filament filters and search).
- Scopes the closure removes (`withoutGlobalScopes()`, `withoutGlobalScope()`, `withTrashed()`, `onlyTrashed()`) are not carried over, so a preset can never drop the tenant scope or a soft-delete scope. Use a Filament filter such as `TrashedFilter` for trashed rows.
- Only `where` constraints are carried over. Model scopes and `whereHas` work. Put ordering in `defaultSort()`, and joins and eager loads in the table query.
- The closure also runs when Filament resolves a record for a row action (it receives the same query scopes with `isResolvingRecord`). It cannot opt out. A two-step action that moves a record out of the active preset fails on its second step.
- The closure runs every time the table query is built, several times per request. Keep it cheap and free of side effects.
- A saved view never contains the hidden query. Quick Save is refused while such a preset is active, unless you set `preset_views.create_from_preset_with_query` (see [Views](views.md#quick-save)).

## Badges and tooltips

- The `badge()`, `badgeColor()` and `tooltip()` closures run at most once per request. They run only for presets shown in the bar, and only when the bar renders.
- Every Livewire request renders the bar again (search, sort, pagination, polling). A badge that queries the database therefore runs on each request.
- Add `->cacheBadgeFor($seconds)` to keep the value in the app cache, per user, tenant and table.

```php
PresetTableView::make()
    ->badge(fn () => Order::where('status', 'late')->count())
    ->badgeColor('danger')
    ->cacheBadgeFor(60);
```

## Preserve parts

Use `preserve*` when a preset should leave some parts alone. This preset only changes the sort and keeps what the user already filtered:

```php
'newest' => PresetTableView::make()
    ->label('Newest first')
    ->defaultSort('created_at', 'desc')
    ->preserveFilters()
    ->preserveSearch(),
```

## Custom icons

Register your own icon alias, then use its key:

```php
use Filament\Support\Facades\FilamentIcon;

FilamentIcon::register(['tablekit::icons.rocket' => 'heroicon-o-rocket-launch']);
```

An unknown key shows no icon. See [Colors and icons](colors-and-icons.md) for more.

## Storage

Presets are pure configuration. Per-user choices (favorite, order, default, hidden) are stored in `tablekit_preferences`. Rows of presets that you remove from the code stay there until the user's rows are deleted. This is harmless and not cleaned up. There is no UI to hide a preset. `is_hidden` is honored when it is set in the database.
