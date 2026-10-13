<?php

use Livewire\Livewire;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrdersHiddenDefaultGroup;
use TableKit\Tests\Fixtures\Pages\ListOrdersPersistent;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithDefaults;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithTabs;

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

function sessionToggles(string $key): array
{
    return collect(session($key))->where('isToggleable', true)->mapWithKeys(fn (array $item) => [$item['name'] => $item['isToggled']])->all();
}

it('starts from the table defaults', function () {
    $component = Livewire::test(ListOrdersWithDefaults::class)
        ->assertSet('tableFilters.hide_cancelled.isActive', true)
        ->assertSet('tableGrouping', 'status:asc');

    expect(toggles($component))->toBe(['notes' => false, 'ordered_at' => true]);
});

it('restores every part to the default state', function () {
    $view = storeView($this->user->id, customState(), ['resource' => ListOrdersWithDefaults::class]);

    $component = Livewire::test(ListOrdersWithDefaults::class)
        ->call('applyTableView', $view->id)
        ->assertSet('tableFilters.hide_cancelled.isActive', false)
        ->assertSet('tableFilters.big.isActive', true)
        ->assertSet('tableSearch', 'ORD')
        ->assertSet('tableSort', 'reference:asc')
        ->assertSet('tableGrouping', 'customer_name:desc')
        ->assertSet('activeTableViewId', $view->id);

    $component->call('applyDefaultTableView')
        ->assertSet('tableFilters.hide_cancelled.isActive', true)
        ->assertSet('tableFilters.big.isActive', false)
        ->assertSet('tableSearch', '')
        ->assertSet('tableColumnSearches', [])
        ->assertSet('tableSort', null)
        ->assertSet('tableGrouping', 'status:asc')
        ->assertSet('activeTableViewId', null)
        ->assertSet('activeTableViewHash', null)
        ->assertCanNotSeeTableRecords(Order::where('status', 'cancelled')->take(10)->get());

    expect(toggles($component))->toBe(['notes' => false, 'ordered_at' => true]);
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
        ->assertSet('tableGrouping', 'customer_name:asc')
        ->call('applyDefaultTableView')
        ->assertSet('tableGrouping', null);

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
        ->and(session($instance->getTableSortSessionKey()))->toBe('amount:desc')
        ->and(sessionToggles($instance->getTableColumnsSessionKey()))->toBe(['notes' => true, 'ordered_at' => false]);

    $component->call('applyDefaultTableView');

    expect(session($instance->getTableFiltersSessionKey())['status']['value'])->toBeNull()
        ->and(session($instance->getTableSearchSessionKey()))->toBe('')
        ->and(session($instance->getTableColumnSearchesSessionKey()))->toBe([])
        ->and(sessionToggles($instance->getTableColumnsSessionKey()))->toBe(['notes' => false, 'ordered_at' => true])
        ->and(session($instance->getTableSortSessionKey()))->toBeNull();
});

it('keeps a persisted grouping in the session in step with applying a view and Default', function () {
    $view = storeView($this->user->id, ['grouping' => ['column' => 'customer_name', 'direction' => 'desc']], ['resource' => ListOrdersPersistent::class]);

    $component = Livewire::test(ListOrdersPersistent::class)->call('applyTableView', $view->id);
    $key = $component->instance()->getTableGroupingSessionKey();

    expect(session($key))->toBe('customer_name:desc');

    $component->call('applyDefaultTableView');

    expect(session($key))->toBeNull();
});

it('puts the tab back to the default when tab tracking is on', function () {
    config(['tablekit.state.tab' => true]);

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
