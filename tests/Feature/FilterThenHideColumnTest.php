<?php

use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersDeferred;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

describe('a column hidden after a filter was applied through the filters panel', function () {
    it('is saved as hidden and comes back on Default', function () {
        $component = Livewire::test(ListOrdersDeferred::class)
            ->set('tableFilters.status.value', 'paid')
            ->call('applyTableFilters')
            ->assertSet('toggledTableColumns', ['notes' => false, 'ordered_at' => true])
            ->set('toggledTableColumns.ordered_at', false)
            ->set('toggledTableColumns.notes', true);

        $view = quickSave($component, 'Filter first');

        expect($view->state->filters['status']['value'])->toBe('paid')
            ->and($view->state->toggledColumns)->toBe(['notes' => true, 'ordered_at' => false]);

        $component->call('applyDefaultTableView')
            ->assertSet('toggledTableColumns', ['notes' => false, 'ordered_at' => true])
            ->call('applyTableView', $view->id)
            ->assertSet('toggledTableColumns', ['notes' => true, 'ordered_at' => false]);

        expect(headerOrder($component->html()))->toContain('notes')->not->toContain('ordered-at');
    });

    it('is saved as hidden when the filters apply live', function () {
        $component = Livewire::test(ListOrders::class)
            ->set('tableFilters.status.value', 'paid')
            ->set('toggledTableColumns.ordered_at', false);

        expect(quickSave($component, 'Live filter first')->state->toggledColumns)->toBe(['notes' => false, 'ordered_at' => false]);

        $component->call('applyDefaultTableView')->assertSet('toggledTableColumns', ['notes' => false, 'ordered_at' => true]);
    });

    it('is saved as hidden on a table that starts from a default preset, also after a reload', function (bool $reload) {
        presets(['default' => PresetTableView::make()->label('All')->default()]);

        $component = Livewire::test(ListOrdersWithPresets::class)
            ->set('tableFilters.status.value', 'paid')
            ->set('toggledTableColumns.ordered_at', false);

        if ($reload) {
            $component = Livewire::test(ListOrdersWithPresets::class);
        }

        $view = quickSave($component, 'Filter first');

        expect($view->state->toggledColumns)->toBe(['notes' => false, 'ordered_at' => false]);

        $component->call('applyDefaultTableView')->assertSet('toggledTableColumns', ['notes' => false, 'ordered_at' => true]);
    })->with([false, true]);
});
