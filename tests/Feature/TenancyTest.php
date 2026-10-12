<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Team;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function useTeamPanel(?Team $tenant): void
{
    Filament::setCurrentPanel(Filament::getPanel('team'));
    Filament::bootCurrentPanel();
    Filament::setTenant($tenant);
}

it('stores the tenant on the view', function () {
    $team = Team::create(['name' => 'Acme']);
    useTeamPanel($team);

    $view = quickSave(Livewire::test(ListOrders::class), 'Tenant view');

    expect($view->tenant_type)->toBe($team->getMorphClass())
        ->and($view->tenant_id)->toBe((string) $team->getKey())
        ->and(strlen($view->tenant_id))->toBe(26);
});

it('stores nulls without a tenant', function () {
    $view = quickSave(Livewire::test(ListOrders::class), 'Plain');

    expect($view->tenant_type)->toBeNull()->and($view->tenant_id)->toBeNull();
});

it('hides views when the tenant changes', function () {
    $x = Team::create(['name' => 'X']);
    $y = Team::create(['name' => 'Y']);

    useTeamPanel($x);
    quickSave(Livewire::test(ListOrders::class), 'X only');
    Livewire::test(ListOrders::class)->assertSee('X only');

    Filament::setTenant($y);
    Livewire::test(ListOrders::class)->assertDontSee('X only');

    Filament::setTenant($x);
    Livewire::test(ListOrders::class)->assertSee('X only');
});

it('does not mix tenant and tenantless views', function () {
    quickSave(Livewire::test(ListOrders::class), 'No tenant');

    useTeamPanel(Team::create(['name' => 'X']));
    Livewire::test(ListOrders::class)->assertDontSee('No tenant');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant(null);
    Livewire::test(ListOrders::class)->assertSee('No tenant');
});

it('applies a tenant view inside the same tenant', function () {
    $team = Team::create(['name' => 'X']);
    useTeamPanel($team);

    $source = Livewire::test(ListOrders::class)->filterTable('status', 'paid');
    $view = quickSave($source, 'Paid');

    Livewire::test(ListOrders::class)
        ->call('applyTableView', $view->id)
        ->assertSet('tableFilters.status.value', 'paid');

    expect(TableView::count())->toBe(1);
});
