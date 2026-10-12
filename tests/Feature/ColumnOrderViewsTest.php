<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Off;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Presets;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function viewWithOrder(array $order, array $extra = []): TableView
{
    return storeView(test()->user->id, ['columnOrder' => $order, ...$extra], ['resource' => ListOrdersPhase3::class]);
}

describe('saving and applying', function () {
    it('stores no order for the code order, exactly as before', function () {
        $state = quickSave(Livewire::test(ListOrdersPhase3::class)->set('toggledTableColumns.notes', true), 'Plain')->state->toArray();

        expect($state)->not->toHaveKey('columnOrder');
    });

    it('stores the order and brings it back', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up')->call('reorderTableViewColumn', 'plain', 'up');
        $view = quickSave($component, 'Ordered');

        expect($view->state->toArray()['columnOrder'])->toBe(['reference', 'customer_name', 'amount', 'status', 'internal', 'team.name', 'notes', 'plain', 'ordered_at']);

        session()->flush();

        $again = Livewire::test(ListOrdersPhase3::class);

        expect(headerOrder($again->html()))->toBe(CODE_HEADERS);

        $again->call('applyTableView', $view->id);

        expect(headerOrder($again->html()))->toBe(['reference', 'customer-name', 'amount', 'status', 'team', 'plain', 'ordered-at'])
            ->and(callProtected($again, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('stores valid json', function () {
        $view = quickSave(Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up'), 'Json');
        $raw = json_decode(DB::table('table_views')->where('id', $view->id)->value('state'), true);

        expect($raw['columnOrder'])->toHaveCount(9)->and($raw['v'])->toBe(1);
    });

    it('resets to the code order when an older view without an order is applied', function () {
        $old = storeView($this->user->id, ['toggledColumns' => ['notes' => true]], ['resource' => ListOrdersPhase3::class]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up')->call('applyTableView', $old->id);

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and(headerOrder($component->html()))->toBe(['reference', 'customer-name', 'status', 'amount', 'team', 'notes', 'ordered-at', 'plain']);
    });

    it('lays out new columns at the end and ignores removed ones', function () {
        $view = viewWithOrder(['status', 'removed', 'amount']);

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(headerOrder($component->html()))->toBe(['status', 'amount', 'reference', 'customer-name', 'team', 'ordered-at', 'plain'])
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('survives junk in the stored order', function () {
        $view = storeView($this->user->id, ['columnOrder' => ['amount', ['x'], 5, null, 'amount', '', str_repeat('z', 400), 'reference']], ['resource' => ListOrdersPhase3::class]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(array_slice($component->get('tableViewColumnOrder'), 0, 3))->toBe(['amount', 'reference', 'customer_name']);
    });

    it('applies the order together with the toggles', function () {
        $view = viewWithOrder(['plain', 'reference'], ['toggledColumns' => ['notes' => true, 'ordered_at' => false, 'team' => ['name' => true]]]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(headerOrder($component->html()))->toBe(['plain', 'reference', 'customer-name', 'status', 'amount', 'team', 'notes']);
    });

    it('marks a view modified when the order changes and clean after reapplying', function () {
        $view = viewWithOrder(['amount', 'reference']);

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();

        $component->call('reorderTableViewColumn', 'plain', 'up');

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();

        $component->call('applyTableView', $view->id);

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();

        $component->call('resetTableViewColumnOrder');

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('does not mark a plain view modified just because the order is the code order', function () {
        $view = quickSave(Livewire::test(ListOrdersPhase3::class), 'Plain');

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id)
            ->call('reorderTableViewColumn', 'amount', 'up')
            ->call('reorderTableViewColumn', 'amount', 'down');

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('is not captured or applied while the feature is off', function () {
        $view = storeView($this->user->id, ['columnOrder' => ['amount', 'reference']], ['resource' => ListOrdersPhase3Off::class]);

        $component = Livewire::test(ListOrdersPhase3Off::class)->call('applyTableView', $view->id);

        expect(headerOrder($component->html()))->toBe(CODE_HEADERS)
            ->and(quickSave($component, 'Off')->state->toArray())->not->toHaveKey('columnOrder');
    });

    it('keeps the order in the session when a view is applied, so a reload shows the same screen', function () {
        $view = viewWithOrder(['amount', 'reference']);

        Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(headerOrder(Livewire::test(ListOrdersPhase3::class)->html())[0])->toBe('amount');
    });
});

describe('Default and presets', function () {
    it('returns to the code order with Default', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up')->call('applyDefaultTableView');

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and(headerOrder($component->html()))->toBe(CODE_HEADERS)
            ->and(headerOrder(Livewire::test(ListOrdersPhase3::class)->html()))->toBe(CODE_HEADERS);
    });

    it('sets order and visibility with defaultColumns', function () {
        phase3Presets(['cols' => PresetTableView::make()->defaultColumns(['amount', 'reference', 'notes'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'cols');

        expect(headerOrder($component->html()))->toBe(['amount', 'reference', 'notes', 'customer-name', 'status', 'plain'])
            ->and($component->get('toggledTableColumns'))->toBe(['team' => ['name' => false], 'notes' => true, 'ordered_at' => false])
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('hides omitted toggleable columns and appends omitted fixed ones', function () {
        phase3Presets(['cols' => PresetTableView::make()->defaultColumns(['status', 'amount'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'cols');

        expect(headerOrder($component->html()))->toBe(['status', 'amount', 'reference', 'customer-name', 'plain']);
    });

    it('leaves the order alone with defaultToggledColumns', function () {
        phase3Presets(['vis' => PresetTableView::make()->defaultToggledColumns(['notes'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('reorderTableViewColumn', 'amount', 'up')->call('applyTablePresetView', 'vis');

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and(headerOrder($component->html()))->toBe(['reference', 'customer-name', 'status', 'amount', 'notes', 'plain']);
    });

    it('resets the order of a preset that defines no columns', function () {
        phase3Presets(['plain' => PresetTableView::make()->defaultSearch('x')]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('reorderTableViewColumn', 'amount', 'up')->call('applyTablePresetView', 'plain');

        expect($component->get('tableViewColumnOrder'))->toBe([]);
    });

    it('keeps order and visibility when the preset preserves toggled columns', function () {
        phase3Presets(['keep' => PresetTableView::make()->defaultColumns(['plain'])->preserveToggledColumns()]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('reorderTableViewColumn', 'amount', 'up')->call('applyTablePresetView', 'keep');

        expect(headerOrder($component->html()))->toBe(['reference', 'customer-name', 'amount', 'status', 'team', 'ordered-at', 'plain']);
    });

    it('applies the columns of a developer default preset on the first load', function () {
        phase3Presets(['start' => PresetTableView::make()->default()->defaultColumns(['status', 'reference'])]);

        expect(headerOrder(Livewire::test(ListOrdersPhase3Presets::class)->html()))->toBe(['status', 'reference', 'customer-name', 'amount', 'plain']);
    });

    it('saves a view on top of a preset with the order', function () {
        phase3Presets(['cols' => PresetTableView::make()->defaultColumns(['amount', 'reference'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'cols');

        expect(array_slice(quickSave($component, 'From preset')->state->toArray()['columnOrder'], 0, 3))->toBe(['amount', 'reference', 'customer_name']);
    });

    it('does not change the builder methods of Phase 2', function () {
        $preset = PresetTableView::make()->defaultToggledColumns(['a'])->defaultColumns(['b', 'a', 'b']);

        expect($preset->getDefaultToggledColumns())->toBe(['b', 'a', 'b'])
            ->and($preset->toState()->columnOrder)->toBe(['b', 'a'])
            ->and(PresetTableView::make()->defaultColumns(null)->getDefinedParts())->toBe([])
            ->and(PresetTableView::make()->defaultColumns([])->getDefinedParts())->toBe(['columns']);
    });
});
