<?php

use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrdersDeferred;
use TableKit\Tests\Fixtures\Pages\ListOrdersReorderableColumnsPresets;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

describe('a column hidden after a filter was applied through the filters panel', function () {
    it('is saved as hidden and comes back on Default', function () {
        $component = Livewire::test(ListOrdersDeferred::class)
            ->set('tableDeferredFilters.status.value', 'paid')
            ->call('applyTableFilters');

        expect(toggles($component))->toBe(['notes' => false, 'ordered_at' => true]);

        setToggles($component, ['ordered_at' => false, 'notes' => true]);

        $view = quickSave($component, 'Filter first');

        expect($view->state->filters['status']['value'])->toBe('paid')
            ->and($view->state->toggledColumns)->toBe(['notes' => true, 'ordered_at' => false]);

        $component->call('applyDefaultTableView');

        expect(toggles($component))->toBe(['notes' => false, 'ordered_at' => true]);

        $component->call('applyTableView', $view->id);

        expect(toggles($component))->toBe(['notes' => true, 'ordered_at' => false])
            ->and($component->html())->toContain('fi-ta-header-cell-notes')->not->toContain('fi-ta-header-cell-ordered-at');
    });

    it('is saved as hidden on a reorderable table that starts from a default preset, also after a reload', function (bool $reload) {
        presets(['default' => PresetTableView::make()->label('All')->default()]);

        $component = Livewire::test(ListOrdersReorderableColumnsPresets::class)
            ->set('tableDeferredFilters.status.value', 'paid')
            ->call('applyTableFilters');

        setToggles($component, ['amount' => false]);

        if ($reload) {
            $component = Livewire::test(ListOrdersReorderableColumnsPresets::class);
        }

        $view = quickSave($component, 'Filter first');

        expect($view->state->toggledColumns)->toBe(['status' => true, 'amount' => false, 'notes' => false]);

        $component->call('applyDefaultTableView');

        expect(toggles($component))->toBe(['status' => true, 'amount' => true, 'notes' => false]);
    })->with([false, true]);
});
