<?php

use Livewire\Livewire;
use TableKit\TableKitPlugin;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithDefaults;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithTabs;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
    $this->view = storeView($this->user->id, ['filters' => ['status' => ['value' => 'paid']]], ['name' => 'Paid']);
});

it('does not remember the active view by default', function () {
    Livewire::test(ListOrders::class)->call('applyTableView', $this->view->id);

    Livewire::test(ListOrders::class)->assertSet('activeTableViewId', null);
});

it('restores the active view on the next visit when enabled in config', function () {
    config(['tablekit.persist_active_view_in_session' => true]);

    Livewire::test(ListOrders::class)->call('applyTableView', $this->view->id);

    Livewire::test(ListOrders::class)
        ->assertSet('activeTableViewId', $this->view->id)
        ->assertSet('tableFilters.status.value', 'paid')
        ->assertCountTableRecords(Order::where('status', 'paid')->count());
});

it('restores the active view when enabled on the plugin', function () {
    TableKitPlugin::get()->persistActiveViewInSession();

    Livewire::test(ListOrders::class)->call('applyTableView', $this->view->id);

    Livewire::test(ListOrders::class)->assertSet('activeTableViewId', $this->view->id);
});

it('remembers a freshly saved view too', function () {
    config(['tablekit.persist_active_view_in_session' => true]);

    $saved = quickSave(Livewire::test(ListOrders::class)->searchTable('ORD-02'), 'Saved now');

    Livewire::test(ListOrders::class)
        ->assertSet('activeTableViewId', $saved->id)
        ->assertSet('tableSearch', 'ORD-02');
});

it('lets url state win over the remembered view', function () {
    config(['tablekit.persist_active_view_in_session' => true]);

    Livewire::test(ListOrders::class)->call('applyTableView', $this->view->id);

    Livewire::withQueryParams(['search' => 'ORD-05'])
        ->test(ListOrders::class)
        ->assertSet('activeTableViewId', null)
        ->assertSet('tableSearch', 'ORD-05')
        ->assertSet('tableFilters.status.value', null);
});

it('ignores and forgets a remembered view that was deleted', function () {
    config(['tablekit.persist_active_view_in_session' => true]);

    Livewire::test(ListOrders::class)->call('applyTableView', $this->view->id);
    $key = 'tablekit.'.md5(ListOrders::class.'||').'.active';

    expect(session($key))->toBe($this->view->id);

    $this->view->delete();

    Livewire::test(ListOrders::class)->assertSet('activeTableViewId', null);

    expect(session()->has($key))->toBeFalse();
});

it('forgets the remembered view on default', function () {
    config(['tablekit.persist_active_view_in_session' => true]);

    Livewire::test(ListOrders::class)
        ->call('applyTableView', $this->view->id)
        ->call('applyDefaultTableView');

    Livewire::test(ListOrders::class)->assertSet('activeTableViewId', null);
});

it('remembers the view per table', function () {
    config(['tablekit.persist_active_view_in_session' => true]);

    Livewire::test(ListOrders::class)->call('applyTableView', $this->view->id);

    Livewire::test(ListOrdersWithDefaults::class)->assertSet('activeTableViewId', null);
});

it('lets a tab in the url win over the remembered view', function () {
    config(['tablekit.persist_active_view_in_session' => true]);

    $view = storeView($this->user->id, ['filters' => ['status' => ['value' => 'paid']]], ['resource' => ListOrdersWithTabs::class]);

    Livewire::test(ListOrdersWithTabs::class)->call('applyTableView', $view->id);

    Livewire::withQueryParams(['tab' => 'paid'])
        ->test(ListOrdersWithTabs::class)
        ->assertSet('activeTableViewId', null)
        ->assertSet('tableFilters.status.value', null);
});
