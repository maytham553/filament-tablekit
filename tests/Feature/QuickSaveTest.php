<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersDeferred;
use Elite\TableViews\Tests\Fixtures\Team;
use Elite\TableViews\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function saveAttempt(Testable $component, array $data)
{
    return closeOpenAction($component)->callAction('quickSaveTableView', $data);
}

it('requires a name', function () {
    saveAttempt(Livewire::test(ListOrders::class), ['name' => '', 'color' => 'primary'])
        ->assertHasActionErrors(['name' => 'required']);

    expect(TableView::count())->toBe(0);
});

it('limits the name length', function () {
    saveAttempt(Livewire::test(ListOrders::class), ['name' => str_repeat('a', 101), 'color' => 'primary'])
        ->assertHasActionErrors(['name' => 'max']);

    expect(TableView::count())->toBe(0);
});

it('requires a color', function () {
    saveAttempt(Livewire::test(ListOrders::class), ['name' => 'X', 'color' => null])
        ->assertHasActionErrors(['color' => 'required']);
});

it('rejects a name the user already has on this table, ignoring case and spacing', function () {
    $component = Livewire::test(ListOrders::class);

    quickSave($component, 'Paid orders');

    saveAttempt($component, ['name' => 'paid ORDERS', 'color' => 'primary'])->assertHasActionErrors(['name']);
    saveAttempt($component, ['name' => ' Paid orders ', 'color' => 'primary'])->assertHasActionErrors(['name']);

    expect(TableView::count())->toBe(1);
});

it('allows the same name for another user', function () {
    quickSave(Livewire::test(ListOrders::class), 'Shared name');

    $this->signIn(User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'x']));

    quickSave(Livewire::test(ListOrders::class), 'Shared name');

    expect(TableView::where('name', 'Shared name')->count())->toBe(2);
});

it('allows the same name on another table', function () {
    quickSave(Livewire::test(ListOrders::class), 'Shared name');
    quickSave(Livewire::test(ListOrdersDeferred::class), 'Shared name');

    expect(TableView::where('name', 'Shared name')->pluck('resource')->unique()->count())->toBe(2);
});

it('allows the same name in another tenant', function () {
    $x = Team::create(['name' => 'X']);
    $y = Team::create(['name' => 'Y']);

    Filament::setCurrentPanel(Filament::getPanel('team'));
    Filament::bootCurrentPanel();

    Filament::setTenant($x);
    quickSave(Livewire::test(ListOrders::class), 'Shared name');
    saveAttempt(Livewire::test(ListOrders::class), ['name' => 'Shared name', 'color' => 'primary'])->assertHasActionErrors(['name']);

    Filament::setTenant($y);
    quickSave(Livewire::test(ListOrders::class), 'Shared name');

    expect(TableView::where('name', 'Shared name')->count())->toBe(2);
});

it('saves the current table state, not whatever the form posts', function () {
    $component = Livewire::test(ListOrders::class)->filterTable('status', 'paid')->sortTable('amount', 'desc');

    $view = quickSave($component, 'Paid by amount');

    expect($view->state->filters['status'])->toBe(['value' => 'paid'])
        ->and($view->state->sortColumn)->toBe('amount')
        ->and($view->user_id)->toBe((string) $this->user->id)
        ->and($view->resource)->toBe(ListOrders::class);
});

it('defaults the form to the first color, no icon and favorite on', function () {
    Livewire::test(ListOrders::class)
        ->mountAction('quickSaveTableView')
        ->assertSet('mountedActions.0.data.color', 'primary')
        ->assertSet('mountedActions.0.data.icon', null)
        ->assertSet('mountedActions.0.data.is_favorite', true);
});

it('saves favorite views by default and keeps non favorites out of the bar', function () {
    $component = Livewire::test(ListOrders::class);

    $favorite = quickSave($component, 'Fav', ['is_favorite' => true]);
    $plain = quickSave($component, 'Plain', ['is_favorite' => false]);

    expect($favorite->is_favorite)->toBeTrue()
        ->and($plain->is_favorite)->toBeFalse();

    Livewire::test(ListOrders::class)->assertSee('Fav')->assertDontSee('Plain');
});

it('treats the icon as optional and stores the chosen one', function () {
    $component = Livewire::test(ListOrders::class);

    expect(quickSave($component, 'No icon')->icon)->toBeNull()
        ->and(quickSave($component, 'Star', ['icon' => 'star'])->icon)->toBe('star')
        ->and(quickSave($component, 'Colored', ['color' => 'danger'])->color)->toBe('danger');
});

it('rejects colors and icons that are not offered', function () {
    $component = Livewire::test(ListOrders::class);

    saveAttempt($component, ['name' => 'X', 'color' => 'hotpink'])->assertHasActionErrors(['color']);
    saveAttempt($component, ['name' => 'X', 'color' => 'primary', 'icon' => 'not-an-icon'])->assertHasActionErrors(['icon']);

    expect(TableView::count())->toBe(0);
});

it('appends new views at the end of the order', function () {
    $component = Livewire::test(ListOrders::class);

    $a = quickSave($component, 'A');
    $b = quickSave($component, 'B');

    expect($b->sort)->toBeGreaterThan($a->sort);
});

it('refuses to save when the session has ended', function () {
    $component = Livewire::test(ListOrders::class)
        ->mountAction('quickSaveTableView')
        ->setActionData(['name' => 'X', 'color' => 'primary']);

    auth()->logout();

    $component->call('callMountedAction')->assertForbidden();

    expect(TableView::count())->toBe(0);
});

it('opens as a modal by default and as a slide over when configured', function () {
    expect(Livewire::test(ListOrders::class)->mountAction('quickSaveTableView')->instance()->getMountedAction()->isModalSlideOver())->toBeFalse();

    config(['table-views.quick_save.slide_over' => true]);

    expect(Livewire::test(ListOrders::class)->mountAction('quickSaveTableView')->instance()->getMountedAction()->isModalSlideOver())->toBeTrue();
});

it('lets the plugin override the slide over setting', function () {
    TableViewsPlugin::get()->quickSaveSlideOver();

    expect(Livewire::test(ListOrders::class)->mountAction('quickSaveTableView')->instance()->getMountedAction()->isModalSlideOver())->toBeTrue();

    TableViewsPlugin::get()->quickSaveSlideOver(fn () => false);

    expect(Livewire::test(ListOrders::class)->mountAction('quickSaveTableView')->instance()->getMountedAction()->isModalSlideOver())->toBeFalse();
});

it('offers only the configured colors and icons', function () {
    TableViewsPlugin::get()->colors(['danger', 'info'])->icons(['star']);

    $component = Livewire::test(ListOrders::class);

    $component->mountAction('quickSaveTableView')->assertSet('mountedActions.0.data.color', 'danger');

    saveAttempt($component, ['name' => 'X', 'color' => 'primary'])->assertHasActionErrors(['color']);
    saveAttempt($component, ['name' => 'X', 'color' => 'info', 'icon' => 'heart'])->assertHasActionErrors(['icon']);

    expect(quickSave($component, 'Fine', ['color' => 'info', 'icon' => 'star'])->icon)->toBe('star');
});

it('refuses a state that is too large', function () {
    config(['table-views.state.max_bytes' => 50]);

    saveAttempt(Livewire::test(ListOrders::class)->searchTable(str_repeat('x', 200)), ['name' => 'Big', 'color' => 'primary'])
        ->assertHasActionErrors(['name']);

    expect(TableView::count())->toBe(0);
});

it('measures the state size in stored bytes, not escaped json', function () {
    config(['table-views.state.max_bytes' => 100000]);

    $component = Livewire::test(ListOrders::class)->searchTable(str_repeat('ع', 100));

    $stored = strlen(quickSave($component, 'Measure')->getRawOriginal('state'));

    config(['table-views.state.max_bytes' => $stored]);
    quickSave($component, 'Exactly fits');

    config(['table-views.state.max_bytes' => $stored - 1]);
    saveAttempt($component, ['name' => 'One byte over', 'color' => 'primary'])->assertHasActionErrors(['name']);

    expect(TableView::count())->toBe(2);
});

it('strips NUL bytes from the saved state', function () {
    $component = Livewire::test(ListOrders::class)->set('tableSearch', "ab\0c");

    $view = quickSave($component, 'Nul');

    expect($view->state->search)->toBe('abc')
        ->and($view->getRawOriginal('state'))->not->toContain('\u0000')
        ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
});
