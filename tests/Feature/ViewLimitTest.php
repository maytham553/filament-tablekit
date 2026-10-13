<?php

use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Support\TableKitOptions;
use TableKit\TableKitPlugin;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersDeferred;
use TableKit\Tests\Fixtures\Team;
use TableKit\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function fillViews(int $count, ?User $user = null, array $attributes = []): void
{
    foreach (range(1, $count) as $i) {
        storeView(($user ?? test()->user)->id, [], ['name' => "Filler {$i}", ...$attributes]);
    }
}

it('defaults to 100 views per table', function () {
    expect(TableKitOptions::resolve()->maxViewsPerTable())->toBe(100);
});

it('refuses a new view at the limit and writes nothing', function () {
    config(['tablekit.max_views_per_table' => 3]);
    fillViews(3);

    saveAttempt(Livewire::test(ListOrders::class), ['name' => 'Fourth', 'color' => 'primary'])
        ->assertHasActionErrors(['name']);

    expect(TableView::count())->toBe(3)
        ->and(TableView::where('name', 'Fourth')->exists())->toBeFalse();
});

it('saves while below the limit and refuses the one after', function () {
    config(['tablekit.max_views_per_table' => 3]);
    fillViews(2);

    $component = Livewire::test(ListOrders::class);

    quickSave($component, 'Third');

    saveAttempt($component, ['name' => 'Fourth', 'color' => 'primary'])->assertHasActionErrors(['name']);

    expect(TableView::count())->toBe(3);
});

it('allows editing a view at the limit', function () {
    config(['tablekit.max_views_per_table' => 2]);
    fillViews(2);
    $view = TableView::first();

    Livewire::test(ListOrders::class)
        ->callAction('editTableView', ['name' => 'Renamed', 'color' => 'primary'], ['viewId' => $view->id])
        ->assertHasNoActionErrors();

    expect($view->fresh()->name)->toBe('Renamed');
});

it('counts only the views of this user, table and tenant', function () {
    config(['tablekit.max_views_per_table' => 2]);

    $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'x']);
    fillViews(5, $other);
    fillViews(5, null, ['resource' => ListOrdersDeferred::class]);

    $team = Team::create(['name' => 'Team']);
    fillViews(5, null, ['tenant_type' => $team->getMorphClass(), 'tenant_id' => $team->id]);

    $component = Livewire::test(ListOrders::class);

    quickSave($component, 'One');
    quickSave($component, 'Two');

    saveAttempt($component, ['name' => 'Three', 'color' => 'primary'])->assertHasActionErrors(['name']);
});

it('reads the limit from the plugin option before config', function () {
    config(['tablekit.max_views_per_table' => 50]);
    TableKitPlugin::get()->maxViewsPerTable(1);
    fillViews(1);

    saveAttempt(Livewire::test(ListOrders::class), ['name' => 'Second', 'color' => 'primary'])->assertHasActionErrors(['name']);

    expect(TableKitOptions::resolve()->maxViewsPerTable())->toBe(1);
});

it('keeps the limit above zero', function () {
    config(['tablekit.max_views_per_table' => 0]);

    expect(TableKitOptions::resolve()->maxViewsPerTable())->toBe(1);
});

it('explains the limit in English and Arabic', function () {
    expect(__('tablekit::validation.too_many_views', ['max' => 5]))->toContain('5')->toContain('Delete one first');

    app()->setLocale('ar');

    expect(__('tablekit::validation.too_many_views', ['max' => 5]))->toContain('5')->not->toContain('tablekit::');
});
