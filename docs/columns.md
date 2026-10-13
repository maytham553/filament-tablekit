# Columns

TableKit saves the column **visibility** and the column **order** in views and presets.

## Filament's column manager

Filament 5 has a native column manager with reordering. Turn it on in the table with `->reorderableColumns()`. TableKit does **not** replace it and builds no reorder UI of its own. It saves and applies what the manager holds.

Filament 5 keeps column order and visibility in one state (the column manager). A view saves both. The Default button resets both.

### What is supported

- Only tables with `reorderableColumns()` and **no columns layout** (Split, Stack, Panel) have an order to save. On any other table, `columnOrder` is neither stored nor applied.
- A column group moves as one block and orders its own columns. Columns never change group.

## In views

- A view stores the order as `columnOrder`: the column names in order. Groups are expanded in place. It is stored only when it differs from the code order, so earlier views stay byte-identical.
- Applying a view puts the columns in that order through `applyTableColumnManager()`, together with the visibility, so headers, cells and the manager agree.
- A view saved without an order **keeps the user's order** when applied.
- The order is part of the "modified" check.
- Columns added in code after the view was saved land after the listed ones, in code order. Names that no longer exist are skipped.

## In presets

| Method | Order | Visibility |
| --- | --- | --- |
| `defaultColumns(['reference', 'amount', 'notes'])` | Listed columns first, in this order. Other columns follow in code order. | Toggleable columns that are not listed are hidden. |
| `defaultToggledColumns(['notes', 'customer.city'])` | The user's order is kept. | Listed toggleable columns are shown. The others are hidden. |

The Default button puts the order back to the code order.

## Which order wins

- A user's own order that Filament kept in the session beats the startup, remembered or URL-restored view's order (like the toggles), until a view is applied by hand.
- The plugin records what the last view apply wrote, per table, in the session. So an order written by a view is not mistaken for the user's change.
- An order equal to what the last view wrote, or to the code order, cannot be told apart from "never changed". The startup view's order then applies on the next load.
- A view saved without an order keeps the user's order. If the user then moves a column and reloads, the plugin still shows the view as modified. It remembers the order of the last apply in the session. A user who cleared the session, or never applied a view in it, sees the view as clean.

## Turn it off

Per table:

```php
public static function columnOrderTableViews(): bool
{
    return false;
}
```

Everywhere: `columnReordering(false)` on the plugin, or `column_reordering.enabled` in the config.
