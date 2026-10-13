<?php

use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use TableKit\TableKitPlugin;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersDeferred;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithoutViews;
use TableKit\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

it('renders the default button, the favorites and the quick save button in order', function () {
    storeView($this->user->id, [], ['name' => 'Beta view', 'sort' => 2]);
    storeView($this->user->id, [], ['name' => 'Alpha view', 'sort' => 1]);

    Livewire::test(ListOrders::class)
        ->assertSeeInOrder(['Default', 'Alpha view', 'Beta view', 'Save view']);
});

it('orders favorites by sort and then by id', function () {
    storeView($this->user->id, [], ['name' => 'Third', 'sort' => 5]);
    storeView($this->user->id, [], ['name' => 'First', 'sort' => 1]);
    storeView($this->user->id, [], ['name' => 'Second', 'sort' => 1]);

    $component = Livewire::test(ListOrders::class);

    expect(callProtected($component, 'getTableViewsForBar')->pluck('name')->all())->toBe(['First', 'Second', 'Third']);
    $component->assertSeeInOrder(['First', 'Second', 'Third']);
});

it('shows only own favorite views of this table', function () {
    $other = User::create(['name' => 'Other', 'email' => 'o@example.test', 'password' => 'x']);

    storeView($this->user->id, [], ['name' => 'Mine']);
    storeView($this->user->id, [], ['name' => 'Not a favorite', 'is_favorite' => false]);
    storeView($this->user->id, [], ['name' => 'Other table', 'resource' => ListOrdersDeferred::class]);
    storeView($other->id, [], ['name' => 'Someone elses']);

    Livewire::test(ListOrders::class)
        ->assertSee('Mine')
        ->assertDontSee('Not a favorite')
        ->assertDontSee('Other table')
        ->assertDontSee('Someone elses');
});

it('shows the new view in the bar right after saving', function () {
    $component = Livewire::test(ListOrders::class);

    quickSave($component, 'Fresh one');

    $component->assertSee('Fresh one');
});

it('can be disabled in config', function () {
    config(['tablekit.favorites_bar.enabled' => false]);

    Livewire::test(ListOrders::class)->assertDontSee('Save view');
});

it('can be disabled on the plugin, also with a closure', function () {
    TableKitPlugin::get()->favoritesBar(false);

    Livewire::test(ListOrders::class)->assertDontSee('Save view');

    TableKitPlugin::get()->favoritesBar(fn () => true);

    Livewire::test(ListOrders::class)->assertSee('Save view');
});

it('lets the plugin win over config', function () {
    config(['tablekit.favorites_bar.enabled' => false]);

    TableKitPlugin::get()->favoritesBar();

    Livewire::test(ListOrders::class)->assertSee('Save view');
});

it('is not rendered on a component without the trait', function () {
    storeView($this->user->id, [], ['name' => 'Hidden here']);

    Livewire::test(ListOrdersWithoutViews::class)
        ->assertSuccessful()
        ->assertDontSee('Save view')
        ->assertDontSee('Hidden here');
});

it('highlights the default button until a view is applied', function () {
    $view = storeView($this->user->id, ['search' => 'ORD-01'], ['name' => 'Search view']);

    $component = Livewire::test(ListOrders::class)->assertSet('activeTableViewId', null);

    expect($component->html())->not->toContain('data-active="true"');

    $component->call('applyTableView', $view->id)
        ->assertSet('activeTableViewId', $view->id)
        ->assertSeeHtml('data-active="true"');

    $component->call('applyDefaultTableView')->assertSet('activeTableViewId', null);

    expect($component->html())->not->toContain('data-active="true"');
});

it('marks the active view as modified once the table changes', function () {
    $view = storeView($this->user->id, ['filters' => ['status' => ['value' => 'paid']]], ['name' => 'Paid']);

    $component = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);

    expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();

    $component->assertDontSee(__('tablekit::favorites-bar.modified'));

    $component->filterTable('status', 'pending');

    expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();

    $component->assertSee(__('tablekit::favorites-bar.modified'));

    $component->filterTable('status', 'paid');

    expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
});

it('does not count a freshly saved view as modified', function () {
    $component = Livewire::test(ListOrders::class)->filterTable('status', 'paid')->sortTable('amount', 'desc');

    $view = quickSave($component, 'Fresh');

    $component->assertSet('activeTableViewId', $view->id);

    expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
});

it('detects a change to every state part', function () {
    $view = storeView($this->user->id, [], ['name' => 'Baseline']);

    $changes = [
        'filter' => fn ($c) => $c->filterTable('big'),
        'search' => fn ($c) => $c->searchTable('ORD'),
        'column search' => fn ($c) => $c->searchTableColumns(['reference' => 'ORD']),
        'sort' => fn ($c) => $c->sortTable('amount'),
        'toggle' => fn ($c) => setToggles($c, ['notes' => true]),
        'grouping' => fn ($c) => $c->set('tableGrouping', 'status:asc'),
    ];

    foreach ($changes as $name => $change) {
        $component = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);
        session()->flush();

        $change($component);

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue($name);
    }
});

it('does not expose its helpers as browser callable methods', function (string $method) {
    expect(fn () => Livewire::test(ListOrders::class)->call($method))->toThrow(MethodNotFoundException::class);
})->with(['getTableViewsForBar', 'getTableViewsResourceKey', 'isActiveTableViewModified']);
