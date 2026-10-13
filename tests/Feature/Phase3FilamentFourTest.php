<?php

use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Columns;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Deferred;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
    config(['table-views.quick_filters.enabled' => true]);
});

describe('multi-sort next to the native column manager', function () {
    it('keeps sorting by every column after the user reorders the columns', function () {
        $component = Livewire::test(ListOrdersPhase3Columns::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->call('setTableViewSortDirection', 'amount', 'desc')
            ->call('addTableViewSort', 'status');

        $expected = expectedReferences([['customer_name', 'asc'], ['amount', 'desc'], ['rank', 'asc']]);

        expect(queryReferences($component))->toBe($expected);

        reorderColumns($component, ['status', 'amount', 'customer_name', ...array_values(array_diff(managerOrder($component), ['status', 'amount', 'customer_name']))]);

        expect(array_slice(headerOrder($component->html()), 0, 3))->toBe(['status', 'amount', 'customer-name'])
            ->and(queryReferences($component))->toBe($expected)
            ->and(preg_match_all('/<th[^>]*data-sort-priority="\d"/', $component->html()))->toBe(3);
    });

    it('keeps the extra sorts when the column manager hides the primary column', function () {
        $component = Livewire::test(ListOrdersPhase3Columns::class)
            ->call('addTableViewSort', 'team.name')
            ->call('addTableViewSort', 'amount');

        setToggles($component, ['team.name' => false]);

        expect(array_column(callProtected($component, 'effectiveTableViewSorts'), 'column'))->toBe(['team.name', 'amount']);
    });

    it('puts the Sort button in the toolbar before the search field', function () {
        $html = Livewire::test(ListOrders::class)->html();

        expect(strpos($html, 'fi-ta-search-field'))->toBeInt()
            ->and(strpos($html, 'data-table-views-sort-trigger'))->toBeInt()
            ->toBeLessThan(strpos($html, 'fi-ta-search-field'));
    });

    it('draws the priority numbers with the header classes of Filament 4', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->call('addTableViewSort', 'reference')->call('addTableViewSort', 'amount')->html();

        expect($html)->toContain('.fi-ta-header-cell[data-sort-priority="2"] .fi-ta-header-cell-sort-btn::after { content: "2"');
    });
});

describe('quick filters on a Filament 4 table', function () {
    it('edits a deferred filter in the popover and applies it with the same Apply button', function () {
        $component = Livewire::test(ListOrders::class)->set('tableFilters.status.value', 'shipped')->call('openTableViewQuickFilter', 'status');

        $path = array_key_first(popoverBindings($component->html(), 'status'));

        expect($path)->toStartWith('tableViewQuickFilterState.status.');

        $component->set($path, 'paid');

        expect($component->get('tableDeferredFilters.status.value'))->toBe('paid')
            ->and($component->get('tableFilters.status.value'))->toBe('shipped');

        $component->assertCountTableRecords(Order::where('status', 'shipped')->count())->call('applyTableFilters');

        expect($component->get('tableFilters.status.value'))->toBe('paid');
        $component->assertCountTableRecords(Order::where('status', 'paid')->count());
    });

    it('renders the Apply button of the popover only for deferred filters', function () {
        $open = fn (string $page) => Livewire::test($page)->call('openTableViewQuickFilter', 'status')->html();

        expect(chipHtml($open(ListOrdersPhase3Deferred::class), 'status'))->toContain('applyTableFilters')
            ->and(chipHtml($open(ListOrdersPhase3::class), 'status'))->not->toContain('applyTableFilters');
    });

    it('reuses the markup and classes of Filament\'s own indicator bar and its clear-all action', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid')->html();

        expect($html)->toContain('class="fi-ta-filter-indicators fi-table-views-quick-filters"')
            ->and($html)->toContain('fi-ta-filter-indicators-label')
            ->and($html)->toContain('fi-ta-filter-indicators-badges-ctn')
            ->and($html)->toContain('data-table-filter-action')->not->toContain('data-table-filter-action="remove-all"')
            ->and($html)->toContain('removeTableFilters');
    });

    it('loads the popover when its panel becomes visible, whichever way it was opened', function () {
        $html = chipHtml(Livewire::test(ListOrdersPhase3::class)->html(), 'status');

        expect($html)->toContain('x-intersect.once="$wire.tableViewOpenQuickFilter === \'status\' || $wire.openTableViewQuickFilter(\'status\')"')
            ->and($html)->not->toContain('x-on:click="$wire.openTableViewQuickFilter');
    });

    it('keeps a click on the remove button of a chip from toggling its popover', function () {
        $html = chipHtml(Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid')->html(), 'status');

        expect($html)->toContain('x-on:mousedown.stop')->and($html)->toContain('fi-badge-delete-btn');
    });

    it('renders its popover as a plain popover, not a menu', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->html();

        expect($html)->toMatch('/class="fi-dropdown fi-table-views-quick-filter"/')
            ->and(preg_match('/x-data="filamentDropdown\(\{ isMenu: false \}\)"[^>]*fi-table-views-quick-filter/s', $html))->toBe(1);
    });
});

describe('the sort priority list', function () {
    it('uses the sortable directive with a drag handle that also moves by keyboard', function () {
        $html = Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'customer_name')
            ->call('addTableViewSort', 'amount')
            ->html();

        expect($html)->toContain('data-table-views-sortable')
            ->and(substr_count($html, 'x-sortable-item="'))->toBe(2)
            ->and(preg_match_all('/\sx-sortable-handle[\s>=\/]/', $html))->toBeGreaterThanOrEqual(2)
            ->and($html)->toContain('x-on:keydown.arrow-up.prevent="moveItem($el, -1)"')
            ->and($html)->toContain('x-on:end.stop="$event.oldIndex !== $event.newIndex');
    });

    it('is a plain popover so the arrow keys on the handles stay with the list', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->html();

        expect($html)->toMatch('/isMenu: false \}\)"\s+class="fi-dropdown fi-table-views-sort"/');
    });
});
