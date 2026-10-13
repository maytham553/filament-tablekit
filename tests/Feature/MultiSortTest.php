<?php

use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersColumnGroups;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersOneSortable;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3DefaultSort;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3HiddenSortable;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Off;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Persistent;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Reorderable;
use Elite\TableViews\Tests\Fixtures\Team;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->signIn();
    $this->seedOrders();
});

function sortsOf($component): array
{
    return callProtected($component, 'effectiveTableViewSorts');
}

function sortTriggerHtml(string $html): string
{
    $start = strpos($html, 'data-table-views-sort-trigger');

    $open = strrpos(substr($html, 0, $start), '<button');

    return substr($html, $open, strpos($html, '>', $start) - $open + 1);
}

describe('building the sort list', function () {
    it('adds a first sort as the primary and applies it through Filament', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'amount');

        expect(sortsOf($component))->toBe([['column' => 'amount', 'direction' => 'asc']])
            ->and(sortColumnOf($component))->toBe('amount')
            ->and(sortDirectionOf($component))->toBe('asc')
            ->and($component->get('tableViewSorts'))->toBe([])
            ->and(isSortedAscending(queryColumn($component, 'amount')))->toBeTrue();
    });

    it('orders by every sort in priority order', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc')
            ->call('addTableViewSort', 'reference');

        expect(sortsOf($component))->toBe([
            ['column' => 'customer_name', 'direction' => 'asc'],
            ['column' => 'amount', 'direction' => 'desc'],
            ['column' => 'reference', 'direction' => 'asc'],
        ])->and(sortColumnOf($component))->toBe('customer_name')
            ->and(queryReferences($component))->toBe(expectedReferences([['customer_name', 'asc'], ['amount', 'desc'], ['reference', 'asc']]));
    });

    it('runs a column\'s own sort closure', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'status')
            ->call('addTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc')
            ->call('addTableViewSort', 'reference');

        expect(queryReferences($component))->toBe(expectedReferences([['rank', 'asc'], ['amount', 'desc'], ['reference', 'asc']]));

        $component->call('setTableViewSortDirection', 'status', 'desc');

        expect(queryReferences($component))->toBe(expectedReferences([['rank', 'desc'], ['amount', 'desc'], ['reference', 'asc']]));
    });

    it('runs the closure of a secondary column too', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'status')
            ->call('addTableViewSort', 'reference');

        expect(queryReferences($component))->toBe(expectedReferences([['customer_name', 'asc'], ['rank', 'asc'], ['reference', 'asc']]));
    });

    it('sorts by a relationship column', function () {
        $teams = [Team::create(['name' => 'Zulu']), Team::create(['name' => 'Alpha']), Team::create(['name' => 'Mike'])];

        Order::query()->get()->each(fn (Order $order) => $order->update(['team_id' => $teams[$order->id % 3]->id]));

        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'team.name')
            ->call('addTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc')
            ->call('addTableViewSort', 'reference');

        $expected = Order::query()->with('team')->get()
            ->sort(fn ($a, $b) => [$a->team->name, -$a->amount, $a->reference] <=> [$b->team->name, -$b->amount, $b->reference])
            ->pluck('reference')->values()->all();

        expect(queryReferences($component))->toBe($expected);
    });

    it('removes a sort and promotes the next one when the primary goes', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('addTableViewSort', 'reference');

        $component->call('removeTableViewSort', 'customer_name');

        expect(sortsOf($component))->toBe([['column' => 'amount', 'direction' => 'asc'], ['column' => 'reference', 'direction' => 'asc']])
            ->and(sortColumnOf($component))->toBe('amount')
            ->and(queryReferences($component))->toBe(expectedReferences([['amount', 'asc'], ['reference', 'asc']]));

        $component->call('removeTableViewSort', 'reference');

        expect(sortsOf($component))->toBe([['column' => 'amount', 'direction' => 'asc']])
            ->and($component->get('tableViewSorts'))->toBe([]);

        $component->call('removeTableViewSort', 'amount');

        expect(sortsOf($component))->toBe([])
            ->and(sortColumnOf($component))->toBeNull()
            ->and(sortDirectionOf($component))->toBeNull();
    });

    it('reorders priorities up and down and stops at the edges', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('addTableViewSort', 'reference');

        $component->call('reorderTableViewSort', 'reference', 'up');

        expect(array_column(sortsOf($component), 'column'))->toBe(['customer_name', 'reference', 'amount']);

        $component->call('reorderTableViewSort', 'reference', 'up');

        expect(array_column(sortsOf($component), 'column'))->toBe(['reference', 'customer_name', 'amount'])
            ->and(sortColumnOf($component))->toBe('reference')
            ->and(queryReferences($component))->toBe(expectedReferences([['reference', 'asc']]));

        $component->call('reorderTableViewSort', 'reference', 'up')->call('reorderTableViewSort', 'amount', 'down');

        expect(array_column(sortsOf($component), 'column'))->toBe(['reference', 'customer_name', 'amount']);

        $component->call('reorderTableViewSort', 'amount', 'up');

        expect(array_column(sortsOf($component), 'column'))->toBe(['reference', 'amount', 'customer_name']);
    });

    it('changes the direction of a secondary sort without touching the primary', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('addTableViewSort', 'reference')
            ->call('setTableViewSortDirection', 'amount', 'desc');

        expect(sortDirectionOf($component))->toBe('asc')
            ->and(sortsOf($component)[1])->toBe(['column' => 'amount', 'direction' => 'desc'])
            ->and(queryReferences($component))->toBe(expectedReferences([['customer_name', 'asc'], ['amount', 'desc'], ['reference', 'asc']]));
    });

    it('clears everything back to the table default', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('clearTableViewSorts');

        expect(sortsOf($component))->toBe([])
            ->and(sortColumnOf($component))->toBeNull()
            ->and($component->get('tableViewSorts'))->toBe([]);
    });

    it('resets the page when the sorts change', function () {
        Livewire::test(ListOrdersPhase3::class)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->call('addTableViewSort', 'amount')
            ->assertSet('paginators.page', 1);
    });

    it('keeps the group order first, then the sorts', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->set('tableGrouping', 'status')
            ->call('addTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc')
            ->call('addTableViewSort', 'reference');

        expect(queryReferences($component))->toBe(expectedReferences([['status', 'asc'], ['amount', 'desc'], ['reference', 'asc']]));
    });

    it('sorts the rendered rows', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc')
            ->call('addTableViewSort', 'reference');

        $component->assertCanSeeTableRecords(
            Order::query()->orderByDesc('amount')->orderBy('reference')->take(25)->get(),
            inOrder: true,
        );
    });

    it('sorts a table export query the same way', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'reference');

        expect($component->instance()->getTableQueryForExport()->pluck('reference')->all())
            ->toBe(expectedReferences([['customer_name', 'asc'], ['reference', 'asc']]));
    });
});

describe('limits and hostile input', function () {
    it('caps the list at the configured maximum', function () {
        config(['table-views.multi_sort.max' => 3]);

        $component = Livewire::test(ListOrdersPhase3::class);

        foreach (['reference', 'customer_name', 'status', 'amount', 'team.name'] as $column) {
            $component->call('addTableViewSort', $column);
        }

        expect(array_column(sortsOf($component), 'column'))->toBe(['reference', 'customer_name', 'status']);
    });

    it('never goes below two as the maximum', function () {
        config(['table-views.multi_sort.max' => 0]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'reference')->call('addTableViewSort', 'amount')->call('addTableViewSort', 'status');

        expect(array_column(sortsOf($component), 'column'))->toBe(['reference', 'amount']);
    });

    it('ignores a column twice', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'amount')->call('addTableViewSort', 'amount');

        expect(sortsOf($component))->toHaveCount(1);
    });

    it('ignores unknown, unsortable, hidden and non-string columns', function (mixed $column) {
        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'reference')->call('addTableViewSort', $column);

        expect(sortsOf($component))->toHaveCount(1);
    })->with([
        'unknown' => 'nope',
        'not sortable' => 'notes',
        'plain column' => 'plain',
        'empty' => '',
        'sql' => 'amount; drop table orders',
        'array' => [['amount']],
        'int' => 5,
        'null' => null,
        'bool' => true,
        'huge' => str_repeat('a', 10000),
    ]);

    it('ignores a bad direction or a column that is not in the list', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'reference')->call('addTableViewSort', 'amount');
        $before = sortsOf($component);

        foreach ([['amount', 'sideways'], ['amount', null], ['amount', ['desc']], ['amount', true], ['status', 'desc'], [['amount'], 'desc'], [null, 'desc']] as [$column, $direction]) {
            $component->call('setTableViewSortDirection', $column, $direction);
        }

        foreach ([['amount', 'left'], ['amount', null], ['status', 'up'], [['amount'], 'up'], ['reference', 'up'], ['amount', 'down']] as [$column, $direction]) {
            $component->call('reorderTableViewSort', $column, $direction);
        }

        $component->call('removeTableViewSort', 'status')->call('removeTableViewSort', ['amount'])->call('removeTableViewSort', null);

        expect(sortsOf($component))->toBe($before);
    });

    it('cannot be set by the browser', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        expect(fn () => $component->set('tableViewSorts', [['column' => 'amount', 'direction' => 'asc'], ['column' => 'reference', 'direction' => 'asc']]))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('ignores stored entries that are no longer sortable', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'reference')->call('addTableViewSort', 'amount');

        $instance = $component->instance();
        $instance->tableViewSorts = [['column' => 'reference', 'direction' => 'asc'], ['column' => 'notes', 'direction' => 'desc'], ['column' => 'amount', 'direction' => 'asc']];

        expect(array_column(callProtected($component, 'effectiveTableViewSorts'), 'column'))->toBe(['reference', 'amount']);
    });
});

describe('header clicks', function () {
    it('replaces the multi-sort with that single sort', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('addTableViewSort', 'reference')
            ->sortTable('status');

        $ranks = ['paid' => 1, 'shipped' => 2, 'pending' => 3, 'cancelled' => 4];

        expect(sortsOf($component))->toBe([['column' => 'status', 'direction' => 'asc']])
            ->and(sortColumnOf($component))->toBe('status')
            ->and(isSortedAscending(array_map(fn (string $status): int => $ranks[$status], queryColumn($component, 'status'))))->toBeTrue();

        $component->call('addTableViewSort', 'amount');

        expect(array_column(sortsOf($component), 'column'))->toBe(['status', 'amount']);
    });

    it('treats a click on the primary column as a replacement too', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->sortTable('customer_name');

        expect(sortsOf($component))->toBe([['column' => 'customer_name', 'direction' => 'desc']])
            ->and(isSortedAscending(array_reverse(queryColumn($component, 'customer_name'))))->toBeTrue();

        $component->sortTable('customer_name');

        expect(sortsOf($component))->toBe([]);
    });

    it('does not bring a dead multi-sort back when its first column is chosen again', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('addTableViewSort', 'reference')
            ->sortTable('amount')
            ->sortTable('customer_name');

        expect(sortsOf($component))->toBe([['column' => 'customer_name', 'direction' => 'asc']])
            ->and($component->get('tableViewSorts'))->toBe([]);
    });

    it('applies the extra sorts only when the primary was chosen, never to the table default sort', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount');

        $instance = $component->instance();
        $instance->tableSort = null;
        $instance->getTable()->defaultSort('customer_name', 'asc');

        $sql = $instance->getFilteredSortedTableQuery()->toSql();

        expect($sql)->toContain('"customer_name" asc')->not->toContain('"amount"');
    });

    it('keeps the multi-sort across unrelated requests', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->searchTable('ORD')
            ->set('tableFilters.status.value', 'paid')
            ->call('gotoPage', 2);

        expect(array_column(sortsOf($component), 'column'))->toBe(['customer_name', 'amount']);
    });

    it('shows the arrow on the primary and a priority on every header', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('addTableViewSort', 'reference');

        $html = $component->html();

        expect($html)->toContain('aria-sort="ascending"')
            ->and(preg_match_all('/<th[^>]*data-sort-priority="(\d)"/', $html, $m))->toBe(3)
            ->and($m[1])->toContain('1', '2', '3');
    });

    it('shows no priority numbers for a single sort', function () {
        expect(Livewire::test(ListOrdersPhase3::class)->sortTable('amount')->html())->not->toContain('data-sort-priority');
    });
});

describe('the Sort button', function () {
    it('renders in the toolbar with the list', function () {
        Livewire::test(ListOrdersPhase3::class)
            ->assertSeeHtml('data-table-views-sort-trigger')
            ->call('addTableViewSort', 'amount')
            ->assertSeeHtml('data-sort-row="amount"')
            ->assertSee('Sort by');
    });

    it('offers only the columns that are not used yet', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'amount')->html();

        expect($html)->toContain('<option value="reference">')
            ->and($html)->not->toContain('<option value="amount">')
            ->and($html)->not->toContain('<option value="notes">')
            ->and($html)->not->toContain('<option value="plain">');
    });

    it('is shown only with two sortable columns', function () {
        Livewire::test(ListOrdersOneSortable::class)->assertDontSeeHtml('data-table-views-sort-trigger');
        Livewire::test(ListOrdersPhase3::class)->assertSeeHtml('data-table-views-sort-trigger');
    });

    it('works with column groups', function () {
        $component = Livewire::test(ListOrdersColumnGroups::class)->call('addTableViewSort', 'status')->call('addTableViewSort', 'amount');

        expect(array_column(sortsOf($component), 'column'))->toBe(['status', 'amount']);
    });
});

describe('drag reorder', function () {
    function threeSorts()
    {
        return Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc')
            ->call('addTableViewSort', 'reference');
    }

    it('applies a dragged priority order and keeps each direction', function () {
        $component = threeSorts()->call('reorderTableViewSorts', ['reference', 'amount', 'customer_name']);

        expect(sortsOf($component))->toBe([
            ['column' => 'reference', 'direction' => 'asc'],
            ['column' => 'amount', 'direction' => 'desc'],
            ['column' => 'customer_name', 'direction' => 'asc'],
        ])->and(sortColumnOf($component))->toBe('reference')
            ->and(sortDirectionOf($component))->toBe('asc')
            ->and(queryReferences($component))->toBe(expectedReferences([['reference', 'asc'], ['amount', 'desc'], ['customer_name', 'asc']]));
    });

    it('changes only a secondary priority without touching the primary', function () {
        $component = threeSorts()->call('reorderTableViewSorts', ['customer_name', 'reference', 'amount']);

        expect(array_column(sortsOf($component), 'column'))->toBe(['customer_name', 'reference', 'amount'])
            ->and(sortColumnOf($component))->toBe('customer_name');
    });

    it('moves by keyboard through the same order call', function () {
        $component = threeSorts()->call('reorderTableViewSorts', ['customer_name', 'reference', 'amount']);

        expect(array_column(sortsOf($component), 'column'))->toBe(['customer_name', 'reference', 'amount']);

        $component->call('reorderTableViewSorts', ['customer_name', 'amount', 'reference']);

        expect(array_column(sortsOf($component), 'column'))->toBe(['customer_name', 'amount', 'reference']);
    });

    it('ignores a tampered order and never errors', function (mixed $columns) {
        $component = threeSorts();
        $before = sortsOf($component);

        $component->call('reorderTableViewSorts', $columns)->assertSuccessful();

        expect(sortsOf($component))->toBe($before);
    })->with([
        'missing' => [['customer_name', 'amount']],
        'empty' => [[]],
        'extra column' => [['customer_name', 'amount', 'reference', 'status']],
        'not sorted yet' => [['customer_name', 'amount', 'status']],
        'not sortable' => [['customer_name', 'amount', 'plain']],
        'duplicate' => [['customer_name', 'customer_name', 'amount']],
        'unknown' => [['customer_name', 'amount', 'nope']],
        'oversized' => [array_map(fn (int $i): string => "c{$i}", range(1, 5000))],
        'huge name' => [['customer_name', 'amount', str_repeat('a', 100000)]],
        'nested' => [['customer_name', 'amount', ['reference']]],
        'null entry' => [['customer_name', 'amount', null]],
        'bool entry' => [['customer_name', 'amount', true]],
        'string' => ['customer_name,amount,reference'],
        'null' => [null],
        'keyed' => [['a' => 'customer_name', 'b' => 'amount', 'c' => 'reference']],
    ]);

    it('has nothing to reorder with one sort or none', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewSorts', ['amount'])->assertSuccessful();

        expect(sortsOf($component))->toBe([]);

        $component->call('addTableViewSort', 'amount')->call('reorderTableViewSorts', ['amount']);

        expect(sortsOf($component))->toBe([['column' => 'amount', 'direction' => 'asc']]);
    });

    it('does nothing when multi sort is off', function () {
        $component = Livewire::test(ListOrdersPhase3Off::class)->call('reorderTableViewSorts', ['amount', 'reference'])->assertSuccessful();

        expect($component->get('tableViewSorts'))->toBe([]);
    });

    it('renders handles and sortable rows only with two or more sorts and no arrow buttons', function () {
        $one = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'amount')->html();

        expect($one)->not->toContain("\$call('reorderTableViewSorts'")->and($one)->not->toMatch('/data-sort-row="amount"\s+x-sortable-item/');

        $html = threeSorts()->html();

        expect($html)->toContain("\$call('reorderTableViewSorts'")
            ->and($html)->toMatch('/data-sort-row="customer_name"\s+x-sortable-item="customer_name"/')
            ->and($html)->toMatch('/data-sort-row="amount"\s+x-sortable-item="amount"/')
            ->and($html)->toMatch('/data-sort-row="reference"\s+x-sortable-item="reference"/')
            ->and($html)->toContain(__('table-views::reorder.handle', ['name' => 'Customer name']))
            ->and($html)->toContain('x-on:keydown.arrow-down.prevent')
            ->and($html)->not->toContain('reorderTableViewSort(');
    });
});

describe('the table\'s default sort', function () {
    it('is kept as the first sort when a sort is added', function () {
        $component = Livewire::test(ListOrdersPhase3DefaultSort::class)->call('addTableViewSort', 'reference');

        expect(sortsOf($component))->toBe([['column' => 'amount', 'direction' => 'desc'], ['column' => 'reference', 'direction' => 'asc']])
            ->and(sortColumnOf($component))->toBe('amount')
            ->and(sortDirectionOf($component))->toBe('desc')
            ->and(queryReferences($component))->toBe(expectedReferences([['amount', 'desc'], ['reference', 'asc']]));
    });

    it('keeps its direction and stays the primary for later sorts', function () {
        $component = Livewire::test(ListOrdersPhase3DefaultSort::class)->call('addTableViewSort', 'customer_name')->call('addTableViewSort', 'reference');

        expect(array_column(sortsOf($component), 'column'))->toBe(['amount', 'customer_name', 'reference'])
            ->and(queryReferences($component))->toBe(expectedReferences([['amount', 'desc'], ['customer_name', 'asc'], ['reference', 'asc']]));
    });

    it('makes the default explicit when its own column is added', function () {
        $component = Livewire::test(ListOrdersPhase3DefaultSort::class)->call('addTableViewSort', 'amount');

        expect(sortsOf($component))->toBe([['column' => 'amount', 'direction' => 'desc']])
            ->and(sortColumnOf($component))->toBe('amount');
    });

    it('does not invent a sort when the table has no string default', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'reference');

        expect(sortsOf($component))->toBe([['column' => 'reference', 'direction' => 'asc']]);
    });
});

describe('persistSortInSession', function () {
    it('keeps the extra sorts for the next visit', function () {
        Livewire::test(ListOrdersPhase3Persistent::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc');

        $again = Livewire::test(ListOrdersPhase3Persistent::class);

        expect(sortsOf($again))->toBe([['column' => 'customer_name', 'direction' => 'asc'], ['column' => 'amount', 'direction' => 'desc']])
            ->and($again->get('tableViewSorts'))->toHaveCount(2)
            ->and(queryReferences($again))->toBe(expectedReferences([['customer_name', 'asc'], ['amount', 'desc']]));
    });

    it('forgets them once a header click ends the multi-sort', function () {
        Livewire::test(ListOrdersPhase3Persistent::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('sortTable', 'reference');

        expect(Livewire::test(ListOrdersPhase3Persistent::class)->get('tableViewSorts'))->toBe([]);
    });

    it('drops a saved list that does not start with the restored sort', function () {
        $component = Livewire::test(ListOrdersPhase3Persistent::class)->call('addTableViewSort', 'customer_name')->call('addTableViewSort', 'amount');

        session()->put(callProtected($component, 'getTableViewSessionKey', 'sorts'), [['column' => 'amount', 'direction' => 'asc'], ['column' => 'reference', 'direction' => 'asc']]);

        expect(Livewire::test(ListOrdersPhase3Persistent::class)->get('tableViewSorts'))->toBe([]);
    });

    it('ignores junk in the session', function (mixed $saved) {
        $component = Livewire::test(ListOrdersPhase3Persistent::class)->call('addTableViewSort', 'customer_name');

        session()->put(callProtected($component, 'getTableViewSessionKey', 'sorts'), $saved);

        expect(Livewire::test(ListOrdersPhase3Persistent::class)->get('tableViewSorts'))->toBe([]);
    })->with(['string' => 'oops', 'numbers' => [[1, 2]], 'unknown' => [[['column' => 'nope'], ['column' => 'x']]]]);

    it('writes nothing for a table that does not persist its sort', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'customer_name')->call('addTableViewSort', 'amount');

        expect(session()->has(callProtected($component, 'getTableViewSessionKey', 'sorts')))->toBeFalse();
    });
});

describe('while records are reordered', function () {
    it('cannot be changed', function () {
        $component = Livewire::test(ListOrdersPhase3Reorderable::class)->call('addTableViewSort', 'customer_name')->call('addTableViewSort', 'amount');

        $component->call('toggleTableReordering');

        expect($component->get('isTableReordering'))->toBeTrue();

        $component->call('addTableViewSort', 'reference')
            ->call('removeTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc')
            ->call('reorderTableViewSort', 'amount', 'up')
            ->call('reorderTableViewSorts', ['amount', 'customer_name'])
            ->call('clearTableViewSorts');

        expect(sortsOf($component))->toBe([['column' => 'customer_name', 'direction' => 'asc'], ['column' => 'amount', 'direction' => 'asc']]);
    });

    it('disables the Sort button', function () {
        $component = Livewire::test(ListOrdersPhase3Reorderable::class);

        expect(sortTriggerHtml($component->html()))->not->toMatch('/\sdisabled[\s=>]/')
            ->and($component->html())->toContain('fi-table-views-sort');

        $component->call('toggleTableReordering');

        expect(sortTriggerHtml($component->html()))->toMatch('/\sdisabled[\s=>]/')
            ->and($component->html())->not->toContain('fi-table-views-sort');
    });

    it('works again afterwards', function () {
        $component = Livewire::test(ListOrdersPhase3Reorderable::class)->call('toggleTableReordering')->call('toggleTableReordering')->call('addTableViewSort', 'amount');

        expect(sortsOf($component))->toBe([['column' => 'amount', 'direction' => 'asc']]);
    });
});

describe('the sort limit', function () {
    it('tells the user when the maximum is reached', function () {
        config(['table-views.multi_sort.max' => 2]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'amount');

        expect($component->html())->not->toContain(__('table-views::sort.limit', ['count' => 2]));

        $component->call('addTableViewSort', 'reference');

        expect($component->html())->toContain(__('table-views::sort.limit', ['count' => 2]));

        $component->call('removeTableViewSort', 'reference');

        expect($component->html())->not->toContain(__('table-views::sort.limit', ['count' => 2]));
    });

    it('says it in Arabic too', function () {
        config(['table-views.multi_sort.max' => 2]);
        app()->setLocale('ar');

        $html = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'amount')->call('addTableViewSort', 'reference')->html();

        expect($html)->toContain(__('table-views::sort.limit', ['count' => 2], 'ar'))->and(__('table-views::sort.limit', ['count' => 2], 'ar'))->not->toBe(__('table-views::sort.limit', ['count' => 2], 'en'));
    });
});

describe('switches', function () {
    it('is off per table', function () {
        $component = Livewire::test(ListOrdersPhase3Off::class)
            ->assertDontSeeHtml('data-table-views-sort-trigger')
            ->call('addTableViewSort', 'amount')
            ->call('addTableViewSort', 'reference');

        expect(sortColumnOf($component))->toBeNull();
    });

    it('is off globally in config', function () {
        config(['table-views.multi_sort.enabled' => false]);

        $component = Livewire::test(ListOrdersPhase3::class)
            ->assertDontSeeHtml('data-table-views-sort-trigger')
            ->call('addTableViewSort', 'amount');

        expect(sortColumnOf($component))->toBeNull();
    });

    it('keeps the plain Filament single sort when off', function () {
        $component = Livewire::test(ListOrdersPhase3Off::class)->sortTable('amount', 'desc');

        $component->assertCanSeeTableRecords(Order::orderByDesc('amount')->take(25)->get(), inOrder: true);
    });
});

function orderBySql(Testable $component): string
{
    $sql = $component->instance()->getFilteredSortedTableQuery()->toSql();

    return substr($sql, strpos($sql, 'order by'));
}

describe('the browser cannot re-run the lifecycle hooks', function () {
    it('keeps one wrapper and the same order when a client calls a trait hook', function (string $hook) {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'reference')
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount');

        $before = orderBySql($component);

        $component->call($hook)->call('addTableViewSort', 'status')->call('removeTableViewSort', 'status');

        expect(orderBySql($component))->toBe($before)
            ->and($before)->toContain('order by "reference" asc, "customer_name" asc, "amount" asc')
            ->and(queryReferences($component))->toBe(expectedReferences([['reference', 'asc'], ['customer_name', 'asc'], ['amount', 'asc']]));
    })->with(['renderingHasTableViews', 'callHasTableViews']);

    it('lets Livewire 4 refuse client calls to mount and boot hooks', function (string $hook) {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'reference')
            ->call('addTableViewSort', 'amount');

        $before = orderBySql($component);

        expect(fn () => $component->call($hook))->toThrow(\Livewire\Exceptions\DirectlyCallingLifecycleHooksNotAllowedException::class)
            ->and(orderBySql($component))->toBe($before);
    })->with(['mountHasTableViews', 'bootedHasTableViews']);

    it('does not wrap the same table twice when the hook runs on the server', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'reference')->call('addTableViewSort', 'amount');
        $before = orderBySql($component);

        callProtected($component, 'bootTableViewSorts');
        callProtected($component, 'bootTableViewSorts');

        expect(orderBySql($component))->toBe($before);
    });

    it('keeps the extra sorts after Filament rebuilds the table in resetTable()', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'reference')
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount');

        $component->call('resetTable');

        expect(queryReferences($component))->toBe(expectedReferences([['reference', 'asc'], ['customer_name', 'asc'], ['amount', 'asc']]))
            ->and($component->instance()->getTable()->getColumn('reference')->getExtraHeaderAttributeBag()->get('data-sort-priority'))->toBe(1);
    });
});

describe('a sortable column the user cannot see', function () {
    it('never reaches the query', function () {
        $component = Livewire::test(ListOrdersPhase3HiddenSortable::class);

        expect(callProtected($component, 'getTableViewSortableColumns'))->not->toHaveKey('customer_id');

        $component->call('addTableViewSort', 'customer_id');
        expect(sortsOf($component))->toBe([])->and(orderBySql($component))->not->toContain('customer_id');

        $component->call('addTableViewSort', 'reference')->call('addTableViewSort', 'customer_id');
        expect(array_column(sortsOf($component), 'column'))->toBe(['reference'])->and(orderBySql($component))->not->toContain('customer_id');

        $component->call('sortTable', 'customer_id');
        expect(sortsOf($component))->toBe([])->and(orderBySql($component))->not->toContain('customer_id');
    });

    it('is dropped from an applied view and from the url', function () {
        $view = storeView(auth()->id(), ['sorts' => [
            ['column' => 'customer_id', 'direction' => 'asc'],
            ['column' => 'reference', 'direction' => 'asc'],
            ['column' => 'amount', 'direction' => 'desc'],
        ]], ['resource' => ListOrdersPhase3HiddenSortable::class]);

        $component = Livewire::test(ListOrdersPhase3HiddenSortable::class)->call('applyTableView', $view->id);

        expect(array_column(sortsOf($component), 'column'))->toBe(['reference', 'amount'])
            ->and(orderBySql($component))->not->toContain('customer_id');

        $reloaded = Livewire::withQueryParams(['sort' => 'customer_id:asc', 'sorts' => 'customer_id:asc,amount:asc'])->test(ListOrdersPhase3HiddenSortable::class);

        expect(orderBySql($reloaded))->not->toContain('customer_id');
    });
});
