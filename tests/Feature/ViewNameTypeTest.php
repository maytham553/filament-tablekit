<?php

use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Tests\Fixtures\Pages\ListOrders;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

it('rejects a name sent as a list on Quick Save without an error page', function (mixed $name) {
    saveAttempt(Livewire::test(ListOrders::class), ['name' => $name, 'color' => 'primary'])
        ->assertHasActionErrors(['name'])
        ->assertSuccessful();

    expect(TableView::count())->toBe(0);
})->with([
    'list' => [['a', 'b']],
    'nested' => [[['x']]],
    'map' => [['k' => 'v']],
]);

it('rejects a name sent as a list on edit and leaves the view alone', function (mixed $name) {
    $view = storeView($this->user->id, [], ['name' => 'Keep me']);

    Livewire::test(ListOrders::class)
        ->mountAction('editTableView', ['viewId' => $view->id])
        ->set('mountedActionsData.0.name', $name)
        ->callMountedAction()
        ->assertHasActionErrors(['name'])
        ->assertSuccessful();

    expect($view->fresh()->name)->toBe('Keep me');
})->with([
    'list' => [['a', 'b']],
    'map' => [['k' => 'v']],
]);
