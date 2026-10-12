<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithTabs;
use Livewire\Livewire;

function capturedState(Closure $setup): array
{
    $component = $setup(Livewire::test(ListOrders::class));

    return quickSave($component, 'captured')->state->toArray();
}

beforeEach(function () {
    $this->signIn();
    $this->seedOrders();
});

it('captures the applied filters', function () {
    $state = capturedState(fn ($c) => $c->filterTable('status', 'paid')->filterTable('big')->filterTable('ordered', ['from' => '2026-02-01', 'until' => null]));

    expect($state['filters'])->toBe([
        'status' => ['value' => 'paid'],
        'big' => ['isActive' => true],
        'ordered' => ['from' => '2026-02-01', 'until' => null],
    ])->and($state['search'])->toBe('')
        ->and($state['sort'])->toBe(['column' => null, 'direction' => null]);
});

it('captures the global search', function () {
    expect(capturedState(fn ($c) => $c->searchTable('ORD-01'))['search'])->toBe('ORD-01');
});

it('captures column searches and drops blank ones', function () {
    $state = capturedState(fn ($c) => $c->searchTableColumns(['reference' => 'ORD-0'])->set('tableColumnSearches.customer_name', ''));

    expect($state['columnSearches'])->toBe(['reference' => 'ORD-0']);
});

it('captures sort column and direction', function () {
    $state = capturedState(fn ($c) => $c->sortTable('amount', 'desc'));

    expect($state['sort'])->toBe(['column' => 'amount', 'direction' => 'desc']);
});

it('captures toggled columns', function () {
    $state = capturedState(fn ($c) => setToggles($c, ['notes' => true, 'ordered_at' => false]));

    expect($state['toggledColumns'])->toBe(['notes' => true, 'ordered_at' => false]);
});

it('captures grouping', function () {
    $state = capturedState(fn ($c) => $c->set('tableGrouping', 'customer_name:desc'));

    expect($state['grouping'])->toBe(['column' => 'customer_name', 'direction' => 'desc']);
});

it('captures a grouping without a direction as ascending', function () {
    $state = capturedState(fn ($c) => $c->set('tableGrouping', 'status'));

    expect($state['grouping'])->toBe(['column' => 'status', 'direction' => 'asc']);
});

it('captures no grouping when nothing is grouped', function () {
    $state = capturedState(fn ($c) => $c->set('tableGrouping', null));

    expect($state['grouping'])->toBe(['column' => null, 'direction' => null]);
});

it('stores the exact expected json when everything is set', function () {
    $component = Livewire::test(ListOrders::class)
        ->filterTable('status', 'paid')
        ->searchTable('ORD')
        ->searchTableColumns(['reference' => 'ORD-0'])
        ->sortTable('amount', 'desc')
        ->set('tableGrouping', 'status:asc');

    setToggles($component, ['notes' => true]);

    $view = quickSave($component, 'everything');

    expect(json_decode($view->getRawOriginal('state'), true))->toBe([
        'v' => 1,
        'filters' => [
            'status' => ['value' => 'paid'],
            'big' => ['isActive' => false],
            'ordered' => ['from' => null, 'until' => null],
        ],
        'search' => 'ORD',
        'columnSearches' => ['reference' => 'ORD-0'],
        'sort' => ['column' => 'amount', 'direction' => 'desc'],
        'toggledColumns' => ['notes' => true, 'ordered_at' => true],
        'grouping' => ['column' => 'status', 'direction' => 'asc'],
    ]);
});

it('stores empty maps as json objects for an untouched table', function () {
    $view = quickSave(Livewire::test(ListOrders::class), 'untouched');

    $raw = json_decode($view->getRawOriginal('state'));

    expect($raw->columnSearches)->toBeObject()
        ->and($raw->search)->toBe('')
        ->and($raw->sort->column)->toBeNull();
});

it('ignores extra action form data and saves the server side table state', function () {
    $component = Livewire::test(ListOrders::class)->searchTable('server side');

    $component->callAction('quickSaveTableView', [
        'name' => 'forged',
        'color' => 'primary',
        'state' => ['search' => 'client side'],
    ]);

    expect(TableView::where('name', 'forged')->firstOrFail()->state->search)->toBe('server side');
});

it('captures the active tab only when the flag is on', function () {
    $component = Livewire::test(ListOrdersWithTabs::class)->set('activeTab', 'paid');

    expect(quickSave($component, 'no-tab')->state->tab)->toBeNull();

    config(['table-views.state.tab' => true]);

    expect(quickSave($component, 'with-tab')->state->tab)->toBe('paid');
});
