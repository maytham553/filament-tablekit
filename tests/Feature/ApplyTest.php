<?php

use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersDeferred;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithTabs;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function applyStored(array $state, string $page = ListOrders::class)
{
    $view = storeView(test()->user->id, $state, ['resource' => $page]);

    return Livewire::test($page)->call('applyTableView', $view->id);
}

it('applies filters', function () {
    applyStored(['filters' => ['status' => ['value' => 'paid'], 'big' => ['isActive' => false], 'ordered' => ['from' => null, 'until' => null]]])
        ->assertSet('tableFilters.status.value', 'paid')
        ->assertCanSeeTableRecords(Order::where('status', 'paid')->get())
        ->assertCanNotSeeTableRecords(Order::where('status', 'pending')->get())
        ->assertCountTableRecords(Order::where('status', 'paid')->count());
});

it('applies toggle and date range filters together', function () {
    $expected = Order::where('status', 'paid')->where('amount', '>=', 500)->whereDate('ordered_at', '>=', now()->startOfYear()->addDays(10)->toDateString())->get();

    applyStored(['filters' => [
        'status' => ['value' => 'paid'],
        'big' => ['isActive' => true],
        'ordered' => ['from' => now()->startOfYear()->addDays(10)->toDateString(), 'until' => null],
    ]])
        ->assertSet('tableFilters.big.isActive', true)
        ->assertCountTableRecords($expected->count())
        ->assertCanSeeTableRecords($expected);
});

it('applies the global search', function () {
    applyStored(['search' => 'ORD-01'])
        ->assertSet('tableSearch', 'ORD-01')
        ->assertCountTableRecords(10)
        ->assertCanSeeTableRecords(Order::where('reference', 'like', 'ORD-01%')->get());
});

it('applies column searches', function () {
    applyStored(['columnSearches' => ['reference' => 'ORD-02']])
        ->assertSet('tableColumnSearches', ['reference' => 'ORD-02'])
        ->assertCountTableRecords(10)
        ->assertCanSeeTableRecords(Order::where('reference', 'like', 'ORD-02%')->get());
});

it('applies the sort', function () {
    applyStored(['sort' => ['column' => 'amount', 'direction' => 'desc']])
        ->assertSet('tableSortColumn', 'amount')
        ->assertSet('tableSortDirection', 'desc')
        ->assertCanSeeTableRecords(Order::orderByDesc('amount')->take(25)->get(), inOrder: true);
});

it('defaults an invalid sort direction to asc', function () {
    applyStored(['sort' => ['column' => 'amount', 'direction' => 'sideways']])
        ->assertSet('tableSortDirection', 'asc')
        ->assertCanSeeTableRecords(Order::orderBy('amount')->take(25)->get(), inOrder: true);
});

it('applies toggled columns', function () {
    applyStored(['toggledColumns' => ['notes' => true, 'ordered_at' => false]])
        ->assertSet('toggledTableColumns', ['notes' => true, 'ordered_at' => false])
        ->assertSee('note 7');
});

it('hides a column the view hides', function () {
    $component = Livewire::test(ListOrders::class)->set('toggledTableColumns.notes', true)->assertSee('note 7');

    $view = storeView($this->user->id, ['toggledColumns' => ['notes' => false, 'ordered_at' => true]]);

    $component->call('applyTableView', $view->id)->assertDontSee('note 7');
});

it('applies grouping', function () {
    $component = applyStored(['grouping' => ['column' => 'status', 'direction' => 'desc']])
        ->assertSet('tableGrouping', 'status')
        ->assertSet('tableGroupingDirection', 'desc');

    expect($component->instance()->getTableRecords()->first()->status)->toBe('shipped');
});

it('applies every part together and matches the saved component', function () {
    $source = Livewire::test(ListOrders::class)
        ->filterTable('status', 'paid')
        ->filterTable('big')
        ->searchTable('ORD')
        ->searchTableColumns(['reference' => 'ORD-'])
        ->sortTable('amount', 'asc')
        ->set('toggledTableColumns.notes', true)
        ->set('toggledTableColumns.ordered_at', false)
        ->set('tableGrouping', 'customer_name')
        ->set('tableGroupingDirection', 'desc');

    $view = quickSave($source, 'round trip');

    session()->flush();

    $target = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);

    foreach (['tableFilters', 'tableSearch', 'tableColumnSearches', 'tableSortColumn', 'tableSortDirection', 'toggledTableColumns', 'tableGrouping', 'tableGroupingDirection'] as $property) {
        expect($target->get($property))->toEqual($source->get($property), $property);
    }

    $target->assertCanSeeTableRecords(
        Order::where('status', 'paid')->where('amount', '>=', 500)->orderBy('amount')->get(),
        inOrder: false,
    );

    expect(callProtected($target, 'isActiveTableViewModified'))->toBeFalse();
});

it('goes back to page one when a view is applied', function () {
    $view = storeView($this->user->id, ['search' => 'ORD']);

    Livewire::test(ListOrders::class)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->call('applyTableView', $view->id)
        ->assertSet('paginators.page', 1);
});

it('applies filters on a table with deferred filters', function () {
    applyStored(['filters' => ['status' => ['value' => 'paid']]], ListOrdersDeferred::class)
        ->assertSet('tableFilters.status.value', 'paid')
        ->assertSet('tableDeferredFilters.status.value', 'paid')
        ->assertCountTableRecords(Order::where('status', 'paid')->count());
});

it('shows filter indicators for an applied view and clears them on default', function () {
    $component = applyStored(['filters' => ['status' => ['value' => 'paid']]]);

    expect($component->instance()->getTable()->getFilterIndicators())->toHaveCount(1);

    $component->call('applyDefaultTableView');

    expect($component->instance()->getTable()->getFilterIndicators())->toHaveCount(0);
});

it('ignores state that no longer matches the table', function () {
    $component = applyStored([
        'filters' => ['removed' => ['value' => 'x'], 'status' => ['value' => 'paid']],
        'columnSearches' => ['gone' => 'x'],
        'sort' => ['column' => 'gone', 'direction' => 'desc'],
        'toggledColumns' => ['gone' => false],
        'grouping' => ['column' => 'nope', 'direction' => 'desc'],
    ])->assertSuccessful();

    expect($component->get('tableFilters'))->not->toHaveKey('removed')
        ->and($component->get('tableFilters.status.value'))->toBe('paid')
        ->and($component->get('tableSortColumn'))->toBeNull()
        ->and($component->get('tableSortDirection'))->toBeNull()
        ->and($component->get('toggledTableColumns'))->toBe(['notes' => false, 'ordered_at' => true])
        ->and($component->get('tableGrouping'))->toBeNull()
        ->and($component->get('tableGroupingDirection'))->toBeNull();
});

it('ignores a sort on a column that is not sortable', function () {
    applyStored(['sort' => ['column' => 'notes', 'direction' => 'asc']])
        ->assertSet('tableSortColumn', null)
        ->assertSet('tableSortDirection', null);
});

it('keeps new toggleable columns visible for a view saved before they existed', function () {
    applyStored(['toggledColumns' => ['notes' => true]])
        ->assertSet('toggledTableColumns', ['notes' => true, 'ordered_at' => true]);
});

it('turns reordering off when a view is applied', function () {
    $view = storeView($this->user->id, ['search' => 'ORD']);

    Livewire::test(ListOrders::class)
        ->set('isTableReordering', true)
        ->call('applyTableView', $view->id)
        ->assertSet('isTableReordering', false);
});

it('applies the saved tab only when the flag is on', function () {
    $view = storeView($this->user->id, ['tab' => 'paid'], ['resource' => ListOrdersWithTabs::class]);

    $component = Livewire::test(ListOrdersWithTabs::class)
        ->call('applyTableView', $view->id)
        ->assertSet('activeTab', 'all');

    config(['table-views.state.tab' => true]);

    $component->call('applyTableView', $view->id)
        ->assertSet('activeTab', 'paid')
        ->assertCountTableRecords(Order::where('status', 'paid')->count());
});

it('ignores a saved tab that no longer exists', function () {
    config(['table-views.state.tab' => true]);

    $view = storeView($this->user->id, ['tab' => 'gone'], ['resource' => ListOrdersWithTabs::class]);

    Livewire::test(ListOrdersWithTabs::class)
        ->call('applyTableView', $view->id)
        ->assertSet('activeTab', 'all');
});

it('marks the applied view as active', function () {
    $view = storeView($this->user->id, ['search' => 'ORD']);

    Livewire::test(ListOrders::class)
        ->call('applyTableView', $view->id)
        ->assertSet('activeTableViewId', $view->id);
});

it('ignores a view id that is not a plain bigint without querying for it', function (mixed $id) {
    DB::enableQueryLog();

    Livewire::test(ListOrders::class)
        ->call('applyTableView', $id)
        ->assertSet('activeTableViewId', null);

    $lookups = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'table_views') && str_contains($sql, '"id" ='));

    expect($lookups)->toBeEmpty();
})->with([
    'letters' => 'abc',
    'overflow digits' => '99999999999999999999',
    'float from json overflow' => 1.0e20,
    'negative' => -1,
    'zero' => 0,
    'decimal string' => '1.5',
    'array' => [[1]],
    'bool' => true,
    'very long' => str_repeat('1', 5000),
    'ulid' => '01J00000000000000000000000',
]);

it('accepts a view id sent as a digit string', function () {
    $view = storeView(test()->user->id, ['search' => 'ORD-01']);

    Livewire::test(ListOrders::class)
        ->call('applyTableView', (string) $view->id)
        ->assertSet('activeTableViewId', $view->id);
});
