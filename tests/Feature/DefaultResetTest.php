<?php

use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersHiddenDefaultGroup;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPersistent;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithDefaults;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithTabs;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function customState(): array
{
    return [
        'filters' => ['hide_cancelled' => ['isActive' => false], 'big' => ['isActive' => true]],
        'search' => 'ORD',
        'columnSearches' => ['reference' => 'ORD-0'],
        'sort' => ['column' => 'reference', 'direction' => 'asc'],
        'toggledColumns' => ['notes' => true, 'ordered_at' => false],
        'grouping' => ['column' => 'customer_name', 'direction' => 'desc'],
    ];
}

it('starts from the table defaults', function () {
    Livewire::test(ListOrdersWithDefaults::class)
        ->assertSet('tableFilters.hide_cancelled.isActive', true)
        ->assertSet('tableGrouping', 'status')
        ->assertSet('toggledTableColumns', ['notes' => false, 'ordered_at' => true]);
});

it('restores every part to the default state', function () {
    $view = storeView($this->user->id, customState(), ['resource' => ListOrdersWithDefaults::class]);

    $component = Livewire::test(ListOrdersWithDefaults::class)
        ->call('applyTableView', $view->id)
        ->assertSet('tableFilters.hide_cancelled.isActive', false)
        ->assertSet('tableFilters.big.isActive', true)
        ->assertSet('tableSearch', 'ORD')
        ->assertSet('tableSortColumn', 'reference')
        ->assertSet('tableGrouping', 'customer_name')
        ->assertSet('activeTableViewId', $view->id);

    $component->call('applyDefaultTableView')
        ->assertSet('tableFilters.hide_cancelled.isActive', true)
        ->assertSet('tableFilters.big.isActive', false)
        ->assertSet('tableSearch', '')
        ->assertSet('tableColumnSearches', [])
        ->assertSet('tableSortColumn', null)
        ->assertSet('tableSortDirection', null)
        ->assertSet('toggledTableColumns', ['notes' => false, 'ordered_at' => true])
        ->assertSet('tableGrouping', 'status')
        ->assertSet('tableGroupingDirection', null)
        ->assertSet('activeTableViewId', null)
        ->assertSet('activeTableViewHash', null)
        ->assertCanNotSeeTableRecords(Order::where('status', 'cancelled')->take(10)->get());
});

it('default is not the same as clearing filters', function () {
    $view = storeView($this->user->id, ['filters' => ['hide_cancelled' => ['isActive' => false]]], ['resource' => ListOrdersWithDefaults::class]);

    Livewire::test(ListOrdersWithDefaults::class)
        ->call('applyTableView', $view->id)
        ->assertSet('tableFilters.hide_cancelled.isActive', false)
        ->call('applyDefaultTableView')
        ->assertSet('tableFilters.hide_cancelled.isActive', true);
});

it('restores a default group that is not selectable', function () {
    $view = storeView($this->user->id, ['grouping' => ['column' => 'customer_name', 'direction' => 'asc']], ['resource' => ListOrdersHiddenDefaultGroup::class]);

    $component = Livewire::test(ListOrdersHiddenDefaultGroup::class)
        ->assertSet('tableGrouping', null)
        ->call('applyTableView', $view->id)
        ->assertSet('tableGrouping', 'customer_name')
        ->call('applyDefaultTableView')
        ->assertSet('tableGrouping', null)
        ->assertSet('tableGroupingDirection', null);

    expect($component->instance()->getTableGrouping()->getId())->toBe('status');
});

it('writes the default state over the persisted session state', function () {
    $view = storeView($this->user->id, [
        'filters' => ['status' => ['value' => 'paid']],
        'search' => 'ORD-01',
        'columnSearches' => ['reference' => 'ORD-0'],
        'sort' => ['column' => 'amount', 'direction' => 'desc'],
        'toggledColumns' => ['notes' => true, 'ordered_at' => false],
    ], ['resource' => ListOrdersPersistent::class]);

    $component = Livewire::test(ListOrdersPersistent::class)->call('applyTableView', $view->id);
    $instance = $component->instance();

    expect(session($instance->getTableFiltersSessionKey())['status']['value'])->toBe('paid')
        ->and(session($instance->getTableSearchSessionKey()))->toBe('ORD-01')
        ->and(session($instance->getTableColumnSearchesSessionKey()))->toBe(['reference' => 'ORD-0'])
        ->and(session($instance->getTableSortSessionKey()))->toBe(['column' => 'amount', 'direction' => 'desc'])
        ->and(session($instance->getTableColumnToggleFormStateSessionKey()))->toBe(['notes' => true, 'ordered_at' => false]);

    $component->call('applyDefaultTableView');

    expect(session($instance->getTableFiltersSessionKey())['status']['value'])->toBeNull()
        ->and(session($instance->getTableSearchSessionKey()))->toBe('')
        ->and(session($instance->getTableColumnSearchesSessionKey()))->toBe([])
        ->and(session($instance->getTableColumnToggleFormStateSessionKey()))->toBe(['notes' => false, 'ordered_at' => true]);

    expect(session($instance->getTableSortSessionKey()))->toBe(['column' => null, 'direction' => null]);
});

it('puts the tab back to the default when tab tracking is on', function () {
    config(['table-views.state.tab' => true]);

    $component = Livewire::test(ListOrdersWithTabs::class);
    $default = $component->get('activeTab');

    $view = storeView($this->user->id, ['tab' => 'paid'], ['resource' => ListOrdersWithTabs::class]);

    $component
        ->call('applyTableView', $view->id)
        ->assertSet('activeTab', 'paid')
        ->call('applyDefaultTableView')
        ->assertSet('activeTab', $default);
});

it('leaves the tab alone when tab tracking is off', function () {
    $component = Livewire::test(ListOrdersWithTabs::class)->set('activeTab', 'paid');

    $component->call('applyDefaultTableView')->assertSet('activeTab', 'paid');
});
