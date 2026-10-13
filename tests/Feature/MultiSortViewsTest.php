<?php

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Presets\PresetTableView;
use TableKit\Support\TableViewState;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Off;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Presets;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function phase3View(array $state, array $attributes = []): TableView
{
    return storeView(test()->user->id, $state, ['resource' => ListOrdersPhase3::class, ...$attributes]);
}

function multiSortComponent(string $page = ListOrdersPhase3::class)
{
    return Livewire::test($page)
        ->call('addTableViewSort', 'customer_name')
        ->call('addTableViewSort', 'amount')
        ->call('setTableViewSortDirection', 'amount', 'desc')
        ->call('addTableViewSort', 'reference');
}

describe('saving', function () {
    it('captures the whole list next to the primary sort', function () {
        $state = quickSave(multiSortComponent(), 'Multi')->state->toArray();

        expect($state['sort'])->toBe(['column' => 'customer_name', 'direction' => 'asc'])
            ->and($state['sorts'])->toBe([
                ['column' => 'customer_name', 'direction' => 'asc'],
                ['column' => 'amount', 'direction' => 'desc'],
                ['column' => 'reference', 'direction' => 'asc'],
            ]);
    });

    it('stores no sorts key for a single sort, exactly as before', function () {
        $state = quickSave(Livewire::test(ListOrdersPhase3::class)->sortTable('amount', 'desc'), 'Single')->state->toArray();

        expect($state)->not->toHaveKey('sorts')
            ->and($state['sort'])->toBe(['column' => 'amount', 'direction' => 'desc']);
    });

    it('stores valid json in the database', function () {
        $view = quickSave(multiSortComponent(), 'Json');
        $raw = json_decode(DB::table('tablekit_views')->where('id', $view->id)->value('state'), true);

        expect($raw['sorts'])->toHaveCount(3)->and($raw['v'])->toBe(1);
    });

    it('captures only the primary when multi-sort is off', function () {
        $component = Livewire::test(ListOrdersPhase3Off::class)->sortTable('amount', 'desc');

        expect(quickSave($component, 'Off')->state->toArray())->not->toHaveKey('sorts');
    });
});

describe('applying', function () {
    it('restores the list, the primary and the query order', function () {
        $view = quickSave(multiSortComponent(), 'Multi');

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(array_column(callProtected($component, 'effectiveTableViewSorts'), 'column'))->toBe(['customer_name', 'amount', 'reference'])
            ->and(sortColumnOf($component))->toBe('customer_name')
            ->and(sortDirectionOf($component))->toBe('asc')
            ->and(queryReferences($component))->toBe(expectedReferences([['customer_name', 'asc'], ['amount', 'desc'], ['reference', 'asc']]))
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('loads a Phase 1 view with only a single sort', function () {
        $view = phase3View(['sort' => ['column' => 'amount', 'direction' => 'desc']]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(callProtected($component, 'effectiveTableViewSorts'))->toBe([['column' => 'amount', 'direction' => 'desc']])
            ->and($component->get('tableViewSorts'))->toBe([])
            ->and(isSortedAscending(array_reverse(queryColumn($component, 'amount'))))->toBeTrue();
    });

    it('replaces a previous multi-sort', function () {
        $view = phase3View(['sort' => ['column' => 'reference', 'direction' => 'desc']]);

        $component = multiSortComponent()->call('applyTableView', $view->id);

        expect(callProtected($component, 'effectiveTableViewSorts'))->toBe([['column' => 'reference', 'direction' => 'desc']])
            ->and($component->get('tableViewSorts'))->toBe([]);
    });

    it('drops entries that are not sortable any more and keeps the rest', function () {
        $view = phase3View(['sorts' => [
            ['column' => 'gone', 'direction' => 'asc'],
            ['column' => 'notes', 'direction' => 'desc'],
            ['column' => 'amount', 'direction' => 'desc'],
            ['column' => 'amount', 'direction' => 'asc'],
            ['column' => 'reference', 'direction' => 'sideways'],
            'oops',
            ['column' => ['x']],
        ]]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(callProtected($component, 'effectiveTableViewSorts'))->toBe([
            ['column' => 'amount', 'direction' => 'desc'],
            ['column' => 'reference', 'direction' => 'asc'],
        ]);
    });

    it('applies at most the configured count', function () {
        config(['tablekit.multi_sort.max' => 2]);

        $view = phase3View(['sorts' => [
            ['column' => 'reference', 'direction' => 'asc'],
            ['column' => 'amount', 'direction' => 'asc'],
            ['column' => 'status', 'direction' => 'asc'],
        ]]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(array_column(callProtected($component, 'effectiveTableViewSorts'), 'column'))->toBe(['reference', 'amount']);
    });

    it('uses only the primary sort when multi-sort is off', function () {
        $view = phase3View([
            'sort' => ['column' => 'customer_name', 'direction' => 'asc'],
            'sorts' => [['column' => 'customer_name', 'direction' => 'asc'], ['column' => 'amount', 'direction' => 'desc']],
        ], ['resource' => ListOrdersPhase3Off::class]);

        $component = Livewire::test(ListOrdersPhase3Off::class)->call('applyTableView', $view->id);

        expect(sortColumnOf($component))->toBe('customer_name')
            ->and($component->get('tableViewSorts'))->toBe([])
            ->and(callProtected($component, 'effectiveTableViewSorts'))->toBe([['column' => 'customer_name', 'direction' => 'asc']]);
    });

    it('marks the view modified after a sort change and clean again after reapplying', function () {
        $view = quickSave(multiSortComponent(), 'Multi');

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        $component->call('reorderTableViewSort', 'reference', 'up');
        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();

        $component->call('applyTableView', $view->id);
        expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();

        $component->call('setTableViewSortDirection', 'amount', 'asc');
        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();

        $component->call('applyTableView', $view->id)->call('removeTableViewSort', 'reference');
        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('returns to the table default with the Default button', function () {
        $component = multiSortComponent()->call('applyDefaultTableView');

        expect(callProtected($component, 'effectiveTableViewSorts'))->toBe([])
            ->and($component->get('tableViewSorts'))->toBe([])
            ->and(sortColumnOf($component))->toBeNull();
    });

    it('works on another page that has no custom sort columns', function () {
        $component = Livewire::test(ListOrders::class)->call('addTableViewSort', 'reference')->call('addTableViewSort', 'amount');

        $view = quickSave($component, 'Plain multi');
        $again = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);

        expect(array_column(callProtected($again, 'effectiveTableViewSorts'), 'column'))->toBe(['reference', 'amount']);
    });
});

describe('presets', function () {
    it('applies a multi-sort preset in order', function () {
        phase3Presets(['multi' => PresetTableView::make()->favorite()->defaultSort(['customer_name' => 'desc', 'amount' => 'asc', 'reference' => 'asc'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'multi');

        expect(callProtected($component, 'effectiveTableViewSorts'))->toBe([
            ['column' => 'customer_name', 'direction' => 'desc'],
            ['column' => 'amount', 'direction' => 'asc'],
            ['column' => 'reference', 'direction' => 'asc'],
        ])->and(queryReferences($component))->toBe(expectedReferences([['customer_name', 'desc'], ['amount', 'asc'], ['reference', 'asc']]))
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('keeps the single sort form working', function () {
        phase3Presets(['single' => PresetTableView::make()->defaultSort('amount', 'desc')]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'single');

        expect(callProtected($component, 'effectiveTableViewSorts'))->toBe([['column' => 'amount', 'direction' => 'desc']]);
    });

    it('does not change the sorts when the preset preserves the sort', function () {
        phase3Presets(['keep' => PresetTableView::make()->defaultSort(['amount' => 'asc', 'reference' => 'asc'])->preserveSort()]);

        $component = multiSortComponent(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'keep');

        expect(array_column(callProtected($component, 'effectiveTableViewSorts'), 'column'))->toBe(['customer_name', 'amount', 'reference']);
    });

    it('resets the sorts of a preset that defines none', function () {
        phase3Presets(['none' => PresetTableView::make()->defaultSearch('x')]);

        $component = multiSortComponent(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'none');

        expect(callProtected($component, 'effectiveTableViewSorts'))->toBe([]);
    });

    it('applies the multi-sort of a developer default preset on the first load', function () {
        phase3Presets(['start' => PresetTableView::make()->default()->defaultSort(['amount' => 'desc', 'reference' => 'asc'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class);

        expect(array_column(callProtected($component, 'effectiveTableViewSorts'), 'column'))->toBe(['amount', 'reference']);
    });

    it('saves a view on top of a multi-sort preset', function () {
        phase3Presets(['multi' => PresetTableView::make()->defaultSort(['amount' => 'desc', 'reference' => 'asc'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'multi');

        expect(quickSave($component, 'From preset')->state->toArray()['sorts'])->toBe([
            ['column' => 'amount', 'direction' => 'desc'],
            ['column' => 'reference', 'direction' => 'asc'],
        ]);
    });
});

describe('state objects', function () {
    it('round trips and keeps old shapes byte-identical', function () {
        $old = TableViewState::fromArray(['sort' => ['column' => 'amount', 'direction' => 'desc']]);

        expect($old->toArray())->not->toHaveKey('sorts')
            ->and($old->sortEntries())->toBe([['column' => 'amount', 'direction' => 'desc']]);

        $multi = TableViewState::fromArray(['sorts' => [['column' => 'a', 'direction' => 'desc'], ['column' => 'b', 'direction' => 'asc']]]);

        expect($multi->sortColumn)->toBe('a')
            ->and($multi->sortDirection)->toBe('desc')
            ->and($multi->toArray()['sort'])->toBe(['column' => 'a', 'direction' => 'desc'])
            ->and(TableViewState::fromArray($multi->toArray())->sortEntries())->toBe($multi->sortEntries());
    });
});
