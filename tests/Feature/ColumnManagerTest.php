<?php

use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersColumnGroups;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersDottedColumn;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersReorderableColumns;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersSplit;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function columnNames(array $items): array
{
    return collect($items)->flatMap(fn (array $item) => $item['type'] === 'group' ? [$item['name'] => collect($item['columns'])->pluck('name')->all()] : [$item['name']])->all();
}

describe('toggled columns in the Filament 4 and 5 column manager', function () {
    it('saves and applies toggles of columns inside a column group', function () {
        $source = Livewire::test(ListOrdersColumnGroups::class);

        expect(toggles($source))->toBe(['amount' => true, 'status' => false, 'notes' => true]);

        setToggles($source, ['amount' => false, 'status' => true]);
        $view = quickSave($source, 'Grouped');

        expect($view->state->toggledColumns)->toBe(['amount' => false, 'status' => true, 'notes' => true]);

        $target = Livewire::test(ListOrdersColumnGroups::class)->call('applyTableView', $view->id);

        expect(toggles($target))->toBe(['amount' => false, 'status' => true, 'notes' => true])
            ->and(callProtected($target, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('hides and shows the real table columns when a view is applied', function () {
        $view = storeView($this->user->id, ['toggledColumns' => ['notes' => false, 'amount' => false, 'status' => true]], ['resource' => ListOrdersColumnGroups::class]);

        $html = Livewire::test(ListOrdersColumnGroups::class)->call('applyTableView', $view->id)->html();

        expect(headerCells($html))->toContain('status')->not->toContain('amount')->not->toContain('notes');
    });

    it('keeps the column order the user chose when a view is applied', function () {
        $source = Livewire::test(ListOrdersReorderableColumns::class);
        $items = $source->get('tableColumns');
        $reordered = [$items[0], $items[2], $items[1], $items[3]];

        $source->call('applyTableColumnManager', $reordered, true);

        expect(columnNames($source->get('tableColumns')))->toBe(['reference', 'amount', 'status', 'notes']);

        $view = storeView($this->user->id, ['toggledColumns' => ['notes' => true, 'status' => false, 'amount' => true]], ['resource' => ListOrdersReorderableColumns::class]);

        $source->call('applyTableView', $view->id);

        expect(columnNames($source->get('tableColumns')))->toBe(['reference', 'amount', 'status', 'notes'])
            ->and(toggles($source))->toBe(['amount' => true, 'status' => false, 'notes' => true])
            ->and(headerCells($source->html()))->toBe(['reference', 'amount', 'notes']);
    });

    it('does not save the column order, only what is shown', function () {
        $source = Livewire::test(ListOrdersReorderableColumns::class);
        $items = $source->get('tableColumns');
        $source->call('applyTableColumnManager', [$items[0], $items[2], $items[1], $items[3]], true);

        $view = quickSave($source, 'Reordered');

        expect(array_keys(json_decode($view->getRawOriginal('state'), true)))->toBe(['v', 'filters', 'search', 'columnSearches', 'sort', 'toggledColumns', 'grouping'])
            ->and(array_keys($view->state->toggledColumns))->toBe(['amount', 'status', 'notes']);
    });

    it('puts the order back to the table order on Default, together with the visibility', function () {
        $source = Livewire::test(ListOrdersReorderableColumns::class);
        $items = $source->get('tableColumns');
        $source->call('applyTableColumnManager', [$items[0], $items[2], $items[1], $items[3]], true);
        setToggles($source, ['notes' => true]);

        $source->call('applyDefaultTableView');

        expect(columnNames($source->get('tableColumns')))->toBe(['reference', 'status', 'amount', 'notes'])
            ->and(toggles($source))->toBe(['status' => true, 'amount' => true, 'notes' => false]);
    });

    it('treats a column added after the view was saved as shown by default', function () {
        $view = storeView($this->user->id, ['toggledColumns' => ['notes' => true]], ['resource' => ListOrdersColumnGroups::class]);

        $target = Livewire::test(ListOrdersColumnGroups::class)->call('applyTableView', $view->id);

        expect(toggles($target))->toBe(['amount' => true, 'status' => false, 'notes' => true]);
    });

    it('reads the nested toggle map a Filament 3 view stored, and the flat one', function (array $saved) {
        $view = storeView($this->user->id, ['toggledColumns' => $saved], ['resource' => ListOrdersDottedColumn::class]);

        $target = Livewire::test(ListOrdersDottedColumn::class)->call('applyTableView', $view->id);

        expect(toggles($target))->toBe(['team.name' => false, 'notes' => true]);
    })->with([
        'nested' => [['team' => ['name' => false], 'notes' => true]],
        'flat' => [['team.name' => false, 'notes' => true]],
    ]);

    it('captures a dotted column name as one key', function () {
        $source = Livewire::test(ListOrdersDottedColumn::class);
        setToggles($source, ['team.name' => false]);

        expect(quickSave($source, 'Dotted')->state->toggledColumns)->toBe(['team.name' => false, 'notes' => false]);
    });

    it('ignores saved toggles of columns that are not toggleable or do not exist', function () {
        $view = storeView($this->user->id, ['toggledColumns' => ['reference' => false, 'gone' => false]], ['resource' => ListOrdersColumnGroups::class]);

        $before = Livewire::test(ListOrdersColumnGroups::class);
        $target = Livewire::test(ListOrdersColumnGroups::class)->call('applyTableView', $view->id);

        expect(toggles($target))->not->toHaveKey('reference')->not->toHaveKey('gone')
            ->and(headerCells($target->html()))->toContain('reference')
            ->and(toggles($before))->toHaveKeys(['amount', 'status', 'notes']);
    });
});

it('applies and captures a table with a columns layout, which has no column manager state', function () {
    $view = storeView(test()->user->id, ['toggledColumns' => ['notes' => true], 'sort' => ['column' => 'amount', 'direction' => 'desc']], ['resource' => ListOrdersSplit::class]);

    $component = Livewire::test(ListOrdersSplit::class)->call('applyTableView', $view->id)->assertSet('tableSort', 'amount:desc');

    expect(toggles($component))->toBe([])
        ->and(quickSave($component, 'Layout')->state->toggledColumns)->toBe([]);
});

it('keeps a table with the default column manager working on the plain page', function () {
    $view = storeView($this->user->id, ['toggledColumns' => ['notes' => true, 'ordered_at' => false]]);

    $component = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);

    expect(toggles($component))->toBe(['notes' => true, 'ordered_at' => false])
        ->and(headerCells($component->html()))->toContain('notes')->not->toContain('ordered-at');
});

function headerCells(string $html): array
{
    preg_match_all('/fi-ta-header-cell-([a-z0-9-]+)/', $html, $matches);

    return array_values(array_unique(array_diff($matches[1], ['sort-btn'])));
}
