<?php

use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Presets\PresetTableView;
use TableKit\Support\TableViewState;
use TableKit\TableKitPlugin;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersColumnGroupsReorderable;
use TableKit\Tests\Fixtures\Pages\ListOrdersReorderableColumns;
use TableKit\Tests\Fixtures\Pages\ListOrdersReorderableColumnsOff;
use TableKit\Tests\Fixtures\Pages\ListOrdersReorderableColumnsPresets;
use TableKit\Tests\Fixtures\Pages\ListOrdersSplit;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function orderedView(array $state, string $page = ListOrdersReorderableColumns::class): TableView
{
    return storeView(test()->user->id, $state, ['resource' => $page]);
}

describe('saving the order the column manager holds', function () {
    it('captures the order together with the visibility', function () {
        $source = reorderColumns(Livewire::test(ListOrdersReorderableColumns::class), ['amount', 'reference', 'notes', 'status']);

        $state = quickSave($source, 'Ordered')->state;

        expect($state->columnOrder)->toBe(['amount', 'reference', 'notes', 'status'])
            ->and($state->toggledColumns)->toEqual(['amount' => true, 'status' => true, 'notes' => false]);
    });

    it('stores nothing while the order is the one defined in code, so earlier views stay byte-identical', function () {
        $state = quickSave(Livewire::test(ListOrdersReorderableColumns::class), 'Plain')->state->toArray();

        expect($state)->not->toHaveKey('columnOrder');
    });

    it('stores nothing again once the columns are moved back to the code order', function () {
        $source = reorderColumns(Livewire::test(ListOrdersReorderableColumns::class), ['amount', 'reference', 'notes', 'status']);
        reorderColumns($source, ['reference', 'status', 'amount', 'notes']);

        expect(quickSave($source, 'Back')->state->toArray())->not->toHaveKey('columnOrder');
    });

    it('does not store an order for a table whose columns cannot be reordered', function () {
        $source = Livewire::test(ListOrders::class);
        setToggles($source, ['notes' => true]);

        expect(quickSave($source, 'Fixed')->state->toArray())->not->toHaveKey('columnOrder');
    });

    it('does not store an order for a table with a column layout', function () {
        $source = Livewire::test(ListOrdersSplit::class);

        expect(quickSave($source, 'Split')->state->toArray())->not->toHaveKey('columnOrder');
    });
});

describe('applying a saved order', function () {
    it('puts the columns in the saved order, in the manager and in the table', function () {
        $view = orderedView(['columnOrder' => ['notes', 'amount', 'reference', 'status'], 'toggledColumns' => ['notes' => true, 'amount' => true, 'status' => true]]);

        $component = Livewire::test(ListOrdersReorderableColumns::class)->call('applyTableView', $view->id);

        expect(managerOrder($component))->toBe(['notes', 'amount', 'reference', 'status'])
            ->and(headerOrder($component->html()))->toBe(['notes', 'amount', 'reference', 'status'])
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies a view saved from another component state', function () {
        $source = reorderColumns(Livewire::test(ListOrdersReorderableColumns::class), ['status', 'notes', 'reference', 'amount']);
        setToggles($source, ['notes' => true]);
        $view = quickSave($source, 'Moved');

        session()->flush();

        $target = Livewire::test(ListOrdersReorderableColumns::class);

        expect(managerOrder($target))->toBe(['reference', 'status', 'amount', 'notes']);

        $target->call('applyTableView', $view->id);

        expect(managerOrder($target))->toBe(['status', 'notes', 'reference', 'amount'])
            ->and(headerOrder($target->html()))->toBe(['status', 'notes', 'reference', 'amount'])
            ->and(toggles($target))->toEqual(['status' => true, 'amount' => true, 'notes' => true])
            ->and(callProtected($target, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('keeps the order the user has when the view saved none', function () {
        $view = orderedView(['toggledColumns' => ['notes' => true]]);

        $component = reorderColumns(Livewire::test(ListOrdersReorderableColumns::class), ['amount', 'reference', 'notes', 'status']);
        $component->call('applyTableView', $view->id);

        expect(managerOrder($component))->toBe(['amount', 'reference', 'notes', 'status'])
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('lists the saved columns first and appends columns added after the view was saved in code order', function () {
        $view = orderedView(['columnOrder' => ['amount', 'reference']]);

        $component = Livewire::test(ListOrdersReorderableColumns::class)->call('applyTableView', $view->id);

        expect(managerOrder($component))->toBe(['amount', 'reference', 'status', 'notes']);
    });

    it('ignores saved columns that no longer exist', function () {
        $view = orderedView(['columnOrder' => ['gone', 'notes', 'also-gone', 'reference', 'status', 'amount']]);

        $component = Livewire::test(ListOrdersReorderableColumns::class)->call('applyTableView', $view->id);

        expect(managerOrder($component))->toBe(['notes', 'reference', 'status', 'amount']);
    });

    it('keeps every column once whatever the saved list holds', function (array $saved) {
        $view = orderedView(['columnOrder' => $saved]);

        $component = Livewire::test(ListOrdersReorderableColumns::class)->call('applyTableView', $view->id)->assertSuccessful();

        expect(collect(managerOrder($component))->sort()->values()->all())->toBe(['amount', 'notes', 'reference', 'status']);
    })->with([
        'duplicates' => [['amount', 'amount', 'amount']],
        'junk' => [[1, null, ['x'], true, 'amount']],
        'empty strings' => [['', 'amount']],
        'huge' => [array_map(fn (int $i): string => "c{$i}", range(1, 5000))],
        'long name' => [[str_repeat('a', 5000), 'amount']],
    ]);

    it('treats a saved order that is not a list as none', function () {
        $state = TableViewState::fromArray(['columnOrder' => 'amount,reference']);

        expect($state->columnOrder)->toBe([]);

        $view = orderedView(['columnOrder' => ['a' => 'amount', 'b' => 'reference']]);
        $component = Livewire::test(ListOrdersReorderableColumns::class)->call('applyTableView', $view->id);

        expect(managerOrder($component))->toBe(['amount', 'reference', 'status', 'notes']);
    });

    it('ignores a saved order on a table whose columns cannot be reordered', function () {
        $view = storeView($this->user->id, ['columnOrder' => ['notes', 'amount'], 'toggledColumns' => ['notes' => true]]);

        $component = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);

        expect(managerOrder($component))->toBe(['reference', 'customer_name', 'status', 'amount', 'notes', 'ordered_at'])
            ->and(toggles($component)['notes'])->toBeTrue();
    });

    it('keeps the header and the rows consistent with the manager', function () {
        $view = orderedView(['columnOrder' => ['amount', 'status', 'reference', 'notes'], 'toggledColumns' => ['notes' => false, 'status' => true, 'amount' => true]]);

        $component = Livewire::test(ListOrdersReorderableColumns::class)->call('applyTableView', $view->id);

        expect(headerOrder($component->html()))->toBe(['amount', 'status', 'reference']);

        $component->assertCanSeeTableRecords(Order::query()->orderBy('id')->take(25)->get());
    });
});

describe('column groups', function () {
    it('moves a group as one block and orders its own columns', function () {
        $source = reorderColumns(Livewire::test(ListOrdersColumnGroupsReorderable::class), ['notes', 'status', 'amount', 'reference']);

        $view = quickSave($source, 'Groups');

        expect($view->state->columnOrder)->toBe(['notes', 'status', 'amount', 'reference']);

        session()->flush();

        $target = Livewire::test(ListOrdersColumnGroupsReorderable::class);

        expect(array_map(fn (array $item): string => $item['name'], $target->get('tableColumns')))->toBe(['reference', 'Money', 'notes']);

        $target->call('applyTableView', $view->id);

        expect(array_map(fn (array $item): string => $item['name'], $target->get('tableColumns')))->toBe(['notes', 'Money', 'reference'])
            ->and(managerOrder($target))->toBe(['notes', 'status', 'amount', 'reference']);
    });

    it('never moves a column into another group', function () {
        $view = orderedView(['columnOrder' => ['status', 'reference', 'amount', 'notes']], ListOrdersColumnGroupsReorderable::class);

        $component = Livewire::test(ListOrdersColumnGroupsReorderable::class)->call('applyTableView', $view->id);

        $groups = collect($component->get('tableColumns'))->mapWithKeys(fn (array $item): array => [$item['name'] => $item['type'] === 'group' ? array_column($item['columns'], 'name') : null])->all();

        expect($groups)->toBe(['Money' => ['status', 'amount'], 'reference' => null, 'notes' => null]);
    });

    it('keeps the toggles of the grouped columns while ordering', function () {
        $view = orderedView(['columnOrder' => ['notes', 'status', 'amount', 'reference'], 'toggledColumns' => ['amount' => false, 'status' => true, 'notes' => true]], ListOrdersColumnGroupsReorderable::class);

        $component = Livewire::test(ListOrdersColumnGroupsReorderable::class)->call('applyTableView', $view->id);

        expect(toggles($component))->toEqual(['amount' => false, 'status' => true, 'notes' => true]);
    });
});

describe('modified detection', function () {
    it('marks a view modified when the order changes and clean again when it is applied', function () {
        $source = reorderColumns(Livewire::test(ListOrdersReorderableColumns::class), ['amount', 'reference', 'notes', 'status']);
        $view = quickSave($source, 'Ordered');

        expect(callProtected($source, 'isActiveTableViewModified'))->toBeFalse();

        reorderColumns($source, ['reference', 'amount', 'notes', 'status']);

        expect(callProtected($source, 'isActiveTableViewModified'))->toBeTrue();

        $source->call('applyTableView', $view->id);

        expect(managerOrder($source))->toBe(['amount', 'reference', 'notes', 'status'])
            ->and(callProtected($source, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('marks a view without an order modified once the user moves a column', function () {
        $view = orderedView(['toggledColumns' => ['notes' => true]]);

        $component = Livewire::test(ListOrdersReorderableColumns::class)->call('applyTableView', $view->id);

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();

        reorderColumns($component, ['amount', 'reference', 'status', 'notes']);

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('does not count a moved column as modified when the table does not support ordering', function () {
        $view = storeView($this->user->id, []);
        $component = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);

        setToggles($component, ['notes' => true]);

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();
    });
});

describe('Default and presets', function () {
    it('puts the order back to the code order on Default', function () {
        $component = reorderColumns(Livewire::test(ListOrdersReorderableColumns::class), ['amount', 'reference', 'notes', 'status']);

        expect(headerOrder($component->html()))->toBe(['amount', 'reference', 'status']);

        $component->call('applyDefaultTableView');

        expect(managerOrder($component))->toBe(['reference', 'status', 'amount', 'notes'])
            ->and(headerOrder($component->html()))->toBe(['reference', 'status', 'amount']);
    });

    it('puts the order back to the code order on Default when a developer default preset applies', function () {
        presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultSearch('ORD')]);

        $component = reorderColumns(Livewire::test(ListOrdersReorderableColumnsPresets::class), ['amount', 'reference', 'notes', 'status']);

        expect(managerOrder($component))->toBe(['amount', 'reference', 'notes', 'status']);

        $component->call('applyDefaultTableView');

        expect($component->get('activeTablePresetKey'))->toBe('dev')
            ->and(managerOrder($component))->toBe(['reference', 'status', 'amount', 'notes'])
            ->and(headerOrder($component->html()))->toBe(['reference', 'status', 'amount']);
    });

    it('renders the code order for a preset whose defaultColumns equal it', function () {
        presets(fn () => ['code' => PresetTableView::make()->label('Code')->favorite()->defaultColumns(['reference', 'status', 'amount', 'notes'])]);

        $component = reorderColumns(Livewire::test(ListOrdersReorderableColumnsPresets::class), ['amount', 'reference', 'notes', 'status']);

        expect(headerOrder($component->html()))->toBe(['amount', 'reference', 'status']);

        $component->call('applyTablePresetView', 'code');

        expect(managerOrder($component))->toBe(['reference', 'status', 'amount', 'notes'])
            ->and(headerOrder($component->html()))->toBe(['reference', 'status', 'amount', 'notes']);
    });

    it('sets order and visibility from defaultColumns', function () {
        presets(fn () => ['cols' => PresetTableView::make()->label('Cols')->favorite()->defaultColumns(['notes', 'reference', 'amount'])]);

        $component = Livewire::test(ListOrdersReorderableColumnsPresets::class)->call('applyTablePresetView', 'cols');

        expect(managerOrder($component))->toBe(['notes', 'reference', 'amount', 'status'])
            ->and(toggles($component))->toEqual(['status' => false, 'amount' => true, 'notes' => true])
            ->and(headerOrder($component->html()))->toBe(['notes', 'reference', 'amount'])
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('keeps the user\'s order with defaultToggledColumns, which sets visibility only', function () {
        presets(fn () => ['vis' => PresetTableView::make()->label('Vis')->favorite()->defaultToggledColumns(['amount'])]);

        $component = reorderColumns(Livewire::test(ListOrdersReorderableColumnsPresets::class), ['amount', 'reference', 'notes', 'status']);
        $component->call('applyTablePresetView', 'vis');

        expect(managerOrder($component))->toBe(['amount', 'reference', 'notes', 'status']);
    });

    it('does not touch the order when the preset preserves the columns', function () {
        presets(fn () => ['keep' => PresetTableView::make()->label('Keep')->favorite()->defaultColumns(['notes', 'reference'])->preserveToggledColumns()]);

        $component = reorderColumns(Livewire::test(ListOrdersReorderableColumnsPresets::class), ['amount', 'reference', 'notes', 'status']);
        $component->call('applyTablePresetView', 'keep');

        expect(managerOrder($component))->toBe(['amount', 'reference', 'notes', 'status']);
    });

    it('saves a view on top of a preset order', function () {
        presets(fn () => ['cols' => PresetTableView::make()->label('Cols')->favorite()->defaultColumns(['notes', 'reference', 'amount'])]);

        $component = Livewire::test(ListOrdersReorderableColumnsPresets::class)->call('applyTablePresetView', 'cols');

        expect(quickSave($component, 'From preset')->state->columnOrder)->toBe(['notes', 'reference', 'amount', 'status']);
    });
});

describe('switches', function () {
    it('saves no order when the table opts out', function () {
        $source = reorderColumns(Livewire::test(ListOrdersReorderableColumnsOff::class), ['amount', 'reference', 'notes', 'status']);

        expect(quickSave($source, 'Off')->state->toArray())->not->toHaveKey('columnOrder');
    });

    it('applies no saved order when the table opts out', function () {
        $view = orderedView(['columnOrder' => ['notes', 'amount']], ListOrdersReorderableColumnsOff::class);
        $target = Livewire::test(ListOrdersReorderableColumnsOff::class)->call('applyTableView', $view->id);

        expect(managerOrder($target))->toBe(['reference', 'status', 'amount', 'notes']);
    });

    it('saves and applies no order when the plugin turns column ordering off', function () {
        TableKitPlugin::get()->columnReordering(false);

        $view = orderedView(['columnOrder' => ['notes', 'amount']]);
        $target = Livewire::test(ListOrdersReorderableColumns::class)->call('applyTableView', $view->id);

        expect(managerOrder($target))->toBe(['reference', 'status', 'amount', 'notes']);

        reorderColumns($target, ['amount', 'reference', 'notes', 'status']);

        expect(quickSave($target, 'Off')->state->toArray())->not->toHaveKey('columnOrder');
    });

    it('is turned off by config too', function () {
        config(['tablekit.column_reordering.enabled' => false]);

        $source = reorderColumns(Livewire::test(ListOrdersReorderableColumns::class), ['amount', 'reference', 'notes', 'status']);

        expect(quickSave($source, 'Off')->state->toArray())->not->toHaveKey('columnOrder');
    });

    it('does not expose the switch to the browser', function () {
        expect(fn () => Livewire::test(ListOrdersReorderableColumns::class)->call('columnOrderTableViews'))->toThrow(MethodNotFoundException::class);
    });
});
