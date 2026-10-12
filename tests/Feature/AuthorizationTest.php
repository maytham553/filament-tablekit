<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersDeferred;
use Elite\TableViews\Tests\Fixtures\Team;
use Elite\TableViews\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function assertUntouched($component): void
{
    $component
        ->assertSet('activeTableViewId', null)
        ->assertSet('tableSearch', '')
        ->assertSet('tableSortColumn', null)
        ->assertSet('tableGrouping', null);
}

it('ignores another users view id', function () {
    $other = User::create(['name' => 'Other', 'email' => 'o@example.test', 'password' => 'x']);
    $view = storeView($other->id, ['search' => 'secret', 'sort' => ['column' => 'amount', 'direction' => 'desc']]);

    assertUntouched(Livewire::test(ListOrders::class)->call('applyTableView', $view->id)->assertSuccessful());
});

it('ignores a view from another table', function () {
    $view = storeView($this->user->id, ['search' => 'x'], ['resource' => ListOrdersDeferred::class]);

    assertUntouched(Livewire::test(ListOrders::class)->call('applyTableView', $view->id));
});

it('ignores a view from another tenant', function () {
    $x = Team::create(['name' => 'X']);
    $y = Team::create(['name' => 'Y']);

    Filament::setCurrentPanel(Filament::getPanel('team'));
    Filament::bootCurrentPanel();

    $view = storeView($this->user->id, ['search' => 'x'], ['tenant_type' => $x->getMorphClass(), 'tenant_id' => $x->id]);

    Filament::setTenant($y);

    assertUntouched(Livewire::test(ListOrders::class)->call('applyTableView', $view->id));

    Filament::setTenant($x);

    Livewire::test(ListOrders::class)->call('applyTableView', $view->id)->assertSet('tableSearch', 'x');
});

it('ignores an unknown id', function () {
    assertUntouched(Livewire::test(ListOrders::class)->call('applyTableView', 99999));
});

it('does not let a client forge the active view id', function () {
    $other = User::create(['name' => 'Other', 'email' => 'o@example.test', 'password' => 'x']);
    $view = storeView($other->id, []);

    Livewire::test(ListOrders::class)->set('activeTableViewId', $view->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('never leaks a view across users in the bar', function () {
    $other = User::create(['name' => 'Other', 'email' => 'o@example.test', 'password' => 'x']);
    storeView($other->id, [], ['name' => 'Private']);

    Livewire::test(ListOrders::class)->assertDontSee('Private');

    $this->signIn($other);

    Livewire::test(ListOrders::class)->assertSee('Private');
});

it('keeps views of the signed in user after the user changes', function () {
    storeView($this->user->id, [], ['name' => 'Mine']);

    expect(TableView::forCurrentContext(ListOrders::class)->count())->toBe(1);

    $this->signIn(User::create(['name' => 'B', 'email' => 'b@example.test', 'password' => 'x']));

    expect(TableView::forCurrentContext(ListOrders::class)->count())->toBe(0);
});
