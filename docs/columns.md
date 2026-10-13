# Columns

TableKit saves the column **visibility** and the column **order** in views and presets.

## The column list

The column toggle dropdown becomes a column list. For each column it shows:

- a show/hide checkbox (toggleable columns only);
- a drag handle;
- a **Reset order** link, when the order is custom.

Filament's trigger button, width and placement stay the same. Only the content is replaced. Tables with no toggleable column get a small **Columns** dropdown with the same list in the toolbar.

Reordering is drag and drop. ArrowUp and ArrowDown on a handle move the column one place by keyboard.

### What is supported

- Only tables with a **flat list of columns** are supported. Column groups, layout components (Split, Stack, Panel) and collapsible layouts are left alone, because "move up" has no clean meaning inside them.
- The list follows the table's `columnToggleFormColumns()`.
- Columns you hide in code with `hidden()` are not listed and keep their slot.
- Columns hidden by the user's toggle are listed, so they can be placed before they are shown again.

### How it works

Filament rebuilds the table on every request and renders headers, cells and summaries from the table's column list. So the order is applied once per request, on the freshly built table: the same `Column` objects, pushed again in the saved order. Column actions, summaries, grouping, bulk selection and record URLs are untouched.

The column list changes Filament's state through its public API only (wrapped sort closures, `Table::columns()`, the cached `toggleTableColumnForm`), so it never collides with `InteractsWithTable`.

## In views

- A view stores the order as `columnOrder`: the column names in order. It is stored only when it differs from the code order, so earlier views stay byte-identical.
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

- The order is stored per user in the session, like Filament's toggled columns.
- A user's own order that is kept in the session beats the startup, remembered or URL-restored view's order, until a view is applied by hand. The plugin records what the last view apply wrote, so an order written by a view is not mistaken for the user's change.
- An order equal to what the last view wrote, or to the code order, cannot be told apart from "never changed". The startup view's order then applies on the next load.

## Turn it off

Per table:

```php
public static function columnOrderTableViews(): bool
{
    return false;
}
```

Everywhere: `columnReordering(false)` on the plugin, or `column_reordering.enabled` in the config.
