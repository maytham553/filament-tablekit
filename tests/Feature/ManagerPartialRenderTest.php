<?php

use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config(['tablekit.view_manager.slide_over' => true]);

    presets(fn () => ['shipped' => PresetTableView::make()->label('Shipped preset')]);

    $this->view = storeView($this->user->id, ['search' => 'one'], ['name' => 'Plain view', 'is_favorite' => false, 'resource' => ListOrdersWithPresets::class]);
});

it('runs with Filament partial rendering active, as in an app', function () {
    $component = Livewire::test(ListOrdersWithPresets::class)->mountAction('manageTableViews');

    expect($component->effects['partials'] ?? [])->toHaveKey('action-modals');
});

it('delivers the slide over rows in the modal partial', function () {
    storeView($this->user->id, ['search' => 'two'], ['name' => 'Second view', 'is_favorite' => false, 'resource' => ListOrdersWithPresets::class]);

    $modal = Livewire::test(ListOrdersWithPresets::class)->mountAction('manageTableViews')->getMountedActionModalHtml();

    expect($modal)->toContain('Plain view')->toContain('Shipped preset')->toContain('x-sortable');
});

it('refreshes the favorites bar behind the slide over after a favorite change', function () {
    $component = Livewire::test(ListOrdersWithPresets::class)->mountAction('manageTableViews');

    expect(substr_count($component->html(), 'wire:key="table-view-'.$this->view->id.'"'))->toBe(0);

    $component->call('toggleTableViewFavorite', 'user', (string) $this->view->id);

    expect($component->effects['partials'] ?? [])->toBe([])
        ->and($component->html())->toContain('wire:key="table-view-'.$this->view->id.'"')
        ->and($component->html())->toContain('data-name="plain view"');
});

it('refreshes the favorites bar after an edit and a delete made in the slide over', function () {
    TableView::query()->whereKey($this->view->id)->update(['is_favorite' => true]);

    $component = Livewire::test(ListOrdersWithPresets::class)
        ->mountAction('manageTableViews')
        ->call('mountAction', 'editTableView', ['viewId' => $this->view->id])
        ->setActionData(['name' => 'Renamed view', 'color' => 'primary'])
        ->callMountedAction();

    expect($component->html())->toContain('Renamed view')->not->toContain('Plain view');

    $component->call('mountAction', 'deleteTableView', ['viewId' => $this->view->id])->callMountedAction();

    expect($component->html())->not->toContain('Renamed view');
});

it('opens the edit modal for the row that was clicked, inside the slide over', function () {
    $component = Livewire::test(ListOrdersWithPresets::class)
        ->mountAction('manageTableViews')
        ->call('mountAction', 'editTableView', ['viewId' => $this->view->id]);

    expect($component->get('mountedActions'))->toHaveCount(2)
        ->and($component->get('mountedActions.1.name'))->toBe('editTableView')
        ->and($component->getMountedActionModalHtml())->toContain('Edit view');
});

it('turns the unsaved changes prompt off for the list and the delete confirmation', function () {
    $component = Livewire::test(ListOrdersWithPresets::class)->mountAction('manageTableViews');

    expect($component->get('mountedActions.0.hasUnsavedChangesAlert'))->toBeFalse();

    $component->call('mountAction', 'deleteTableView', ['viewId' => $this->view->id]);

    expect($component->get('mountedActions.1.hasUnsavedChangesAlert'))->toBeFalse();
});
