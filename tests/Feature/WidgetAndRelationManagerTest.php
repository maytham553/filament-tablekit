<?php

use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Tests\Fixtures\Customer;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\EditCustomer;
use TableKit\Tests\Fixtures\RelationManagers\OrdersRelationManager;
use TableKit\Tests\Fixtures\Widgets\OrdersWidget;
use TableKit\Tests\Fixtures\Widgets\SharedKeyTwinWidget;
use TableKit\Tests\Fixtures\Widgets\SharedKeyWidget;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
    $this->customer = Customer::create(['name' => 'Acme']);
    Order::query()->limit(30)->update(['customer_id' => $this->customer->id]);
});

function relationManager(Customer $customer)
{
    return Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => EditCustomer::class]);
}

it('renders the bar on a table widget', function () {
    Livewire::test(OrdersWidget::class)
        ->assertSuccessful()
        ->assertSee('Default')
        ->assertSee('Save view');
});

it('saves and applies a view on a table widget', function () {
    $source = Livewire::test(OrdersWidget::class)
        ->filterTable('status', 'paid')
        ->sortTable('amount', 'desc')
        ->set('toggledTableColumns.amount', false);

    $view = quickSave($source, 'Widget view');

    expect($view->resource)->toBe(OrdersWidget::class)
        ->and($view->state->toggledColumns)->toBe(['amount' => false]);

    session()->flush();

    Livewire::test(OrdersWidget::class)
        ->assertSee('Widget view')
        ->call('applyTableView', $view->id)
        ->assertSet('tableFilters.status.value', 'paid')
        ->assertSet('tableSortColumn', 'amount')
        ->assertSet('toggledTableColumns', ['amount' => false])
        ->assertCountTableRecords(Order::where('status', 'paid')->count());
});

it('renders the bar on a relation manager', function () {
    relationManager($this->customer)
        ->assertSuccessful()
        ->assertSee('Default')
        ->assertSee('Save view');
});

it('saves and applies a view on a relation manager', function () {
    $source = relationManager($this->customer)
        ->filterTable('status', 'paid')
        ->searchTable('ORD-0');

    $view = quickSave($source, 'Relation view');

    expect($view->resource)->toBe(OrdersRelationManager::class);

    session()->flush();

    $expected = $this->customer->orders()->where('status', 'paid')->where('reference', 'like', '%ORD-0%')->get();

    relationManager($this->customer)
        ->assertSee('Relation view')
        ->call('applyTableView', $view->id)
        ->assertSet('tableFilters.status.value', 'paid')
        ->assertSet('tableSearch', 'ORD-0')
        ->assertCanSeeTableRecords($expected)
        ->assertCountTableRecords($expected->count());
});

it('keeps views separate per component class', function () {
    quickSave(Livewire::test(OrdersWidget::class), 'Widget only');
    quickSave(relationManager($this->customer), 'Relation only');

    Livewire::test(OrdersWidget::class)->assertSee('Widget only')->assertDontSee('Relation only');
    relationManager($this->customer)->assertSee('Relation only')->assertDontSee('Widget only');

    expect(TableView::pluck('resource')->unique()->all())->toHaveCount(2);
});

it('lets a component override its resource key', function () {
    $view = quickSave(Livewire::test(SharedKeyWidget::class), 'Shared one');

    expect($view->resource)->toBe('shared-orders');

    Livewire::test(SharedKeyTwinWidget::class)->assertSee('Shared one');
    Livewire::test(OrdersWidget::class)->assertDontSee('Shared one');
});

it('resets a widget to its defaults', function () {
    Livewire::test(OrdersWidget::class)
        ->filterTable('status', 'paid')
        ->searchTable('x')
        ->call('applyDefaultTableView')
        ->assertSet('tableFilters.status.value', null)
        ->assertSet('tableSearch', '');
});
