# Testing

Examples for testing a table that uses TableKit with Livewire. The calls are the same ones the buttons in the browser make.

## Set up

Use an in-memory SQLite database in `phpunit.xml`. The TableKit migrations load by themselves, so `RefreshDatabase` creates the tables:

```xml
<php>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
</php>
```

Sign in a user and test the page class:

```php
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});
```

If your app owns the migrations (`load_migrations` is `false`), run your own copies in the test setup as usual.

## Quick Save

```php
use TableKit\Models\TableView;

it('saves the current table state as a view', function () {
    Livewire::test(ListOrders::class)
        ->set('tableFilters.status.value', 'shipped')
        ->callAction('quickSaveTableView', data: ['name' => 'Shipped', 'color' => 'primary'])
        ->assertHasNoActionErrors();

    expect(TableView::where('name', 'Shipped')->exists())->toBeTrue();
});
```

Pass `icon` and `is_favorite` (add to the favorites bar) in `data` if you need them. A name that is taken, a color that is not in the list, or a full table (`max_views_per_table`) comes back as an action error (`assertHasActionErrors()`).

## Apply a preset, a view or the default

```php
it('applies a preset', function () {
    Livewire::test(ListOrders::class)
        ->call('applyTablePresetView', 'shipped')
        ->assertSet('activeTablePresetKey', 'shipped')
        ->assertSet('tableFilters.status.value', 'shipped');
});

it('applies a saved view', function () {
    $view = TableView::first();

    Livewire::test(ListOrders::class)
        ->call('applyTableView', $view->id)
        ->assertSet('activeTableViewId', $view->id);
});

it('goes back to the default', function () {
    Livewire::test(ListOrders::class)
        ->call('applyTablePresetView', 'shipped')
        ->call('applyDefaultTableView')
        ->assertSet('activeTablePresetKey', null)
        ->assertSet('activeTableViewId', null);
});
```

## State properties

These public properties are what you can assert on:

| Property | What it holds |
| --- | --- |
| `activeTablePresetKey` | The key of the active preset, or `null`. |
| `activeTableViewId` | The id of the active saved view, or `null`. |
| `tableViewSorts` | The multi-sort list: `[['column' => 'amount', 'direction' => 'asc'], ...]`. |
| `tableViewColumnOrder` | The column order made in the column list. `[]` means the code order. |
| `tableViewFavoriteFilters` | Pinned quick filters. `null` means the developer's `favorite()` filters. |

```php
it('sorts by several columns', function () {
    Livewire::test(ListOrders::class)
        ->call('addTableViewSort', 'amount')
        ->call('addTableViewSort', 'reference')
        ->assertCount('tableViewSorts', 2);
});
```

Other public methods a test can call, the same ones the buttons call: `removeTableViewSort`, `setTableViewSortDirection`, `clearTableViewSorts` (multi-sort) and `togglePinnedTableViewFilter` (pinned quick filters). Methods that are `protected`, such as `getPresetTableViews()`, cannot be called from a test the way a browser would.
