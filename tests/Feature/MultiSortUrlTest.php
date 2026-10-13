<?php

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Presets;
use TableKit\Tests\Fixtures\Widgets\OrdersWidget;

function reloadPhase3(array $query): Testable
{
    return Livewire::withQueryParams($query)->test(ListOrdersPhase3Presets::class);
}

function sortColumnsOf(Testable $component): array
{
    return array_column(callProtected($component, 'effectiveTableViewSorts'), 'column');
}

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

it('writes the extra sorts to the url and a reload shows the same screen', function () {
    $mounted = Livewire::test(ListOrdersPhase3Presets::class);
    $url = browserUrl($mounted);

    $mounted->call('addTableViewSort', 'customer_name')->call('addTableViewSort', 'amount')->call('setTableViewSortDirection', 'amount', 'desc')->call('addTableViewSort', 'reference');

    $query = $url($mounted);

    expect($query)->toMatchArray([
        'tableSortColumn' => 'customer_name',
        'tableSortDirection' => 'asc',
        'tableSorts' => 'customer_name:asc,amount:desc,reference:asc',
    ]);

    $reloaded = reloadPhase3($query);

    expect(callProtected($reloaded, 'effectiveTableViewSorts'))->toBe(callProtected($mounted, 'effectiveTableViewSorts'))
        ->and(queryReferences($reloaded))->toBe(queryReferences($mounted))
        ->and(queryReferences($reloaded))->toBe(expectedReferences([['customer_name', 'asc'], ['amount', 'desc'], ['reference', 'asc']]));
});

it('adds no extra parameter for a single sort', function () {
    $mounted = Livewire::test(ListOrdersPhase3Presets::class);
    $url = browserUrl($mounted);

    $mounted->call('sortTable', 'amount', 'desc');

    expect($url($mounted))->toMatchArray(['tableSortColumn' => 'amount', 'tableSortDirection' => 'desc'])->not->toHaveKey('tableSorts');

    $mounted->call('addTableViewSort', 'reference')->call('removeTableViewSort', 'reference');

    expect($url($mounted))->not->toHaveKey('tableSorts');
});

it('encodes column names that contain the separators', function () {
    $component = Livewire::test(ListOrdersPhase3Presets::class)->call('addTableViewSort', 'team.name')->call('addTableViewSort', 'amount');

    $url = callProtected($component, 'serializeTableViewSortsForUrl');

    expect($url)->toBe('team.name:asc,amount:asc');

    $reloaded = reloadPhase3(['tableSortColumn' => 'team.name', 'tableSortDirection' => 'asc', 'tableSorts' => $url]);

    expect(sortColumnsOf($reloaded))->toBe(['team.name', 'amount']);
});

describe('ignored when they do not fit', function () {
    it('ignores extra sorts that do not start with the primary from the url', function (array $query) {
        $reloaded = reloadPhase3($query);
        $sorts = callProtected($reloaded, 'effectiveTableViewSorts');

        expect($reloaded->get('tableViewSorts'))->toBe([])
            ->and($sorts === [] || count($sorts) === 1)->toBeTrue();
    })->with([
        'other primary' => [['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => 'reference:asc,amount:asc']],
        'other direction' => [['tableSortColumn' => 'amount', 'tableSortDirection' => 'desc', 'tableSorts' => 'amount:asc,reference:asc']],
        'no primary' => [['tableSorts' => 'amount:asc,reference:asc']],
        'a single entry' => [['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => 'amount:asc']],
        'garbage' => [['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => '::,,,%00']],
        'unknown columns' => [['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => 'amount:asc,nope:asc,notes:desc']],
        'huge' => [['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => str_repeat('amount:asc,', 1000)]],
        'array' => [['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => ['amount', 'reference']]],
        'empty' => [['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => '']],
    ]);

    it('keeps only the valid extra sorts', function () {
        $reloaded = reloadPhase3(['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => 'amount:asc,nope:asc,reference:desc,amount:desc,notes:asc']);

        expect(callProtected($reloaded, 'effectiveTableViewSorts'))->toBe([['column' => 'amount', 'direction' => 'asc'], ['column' => 'reference', 'direction' => 'desc']]);
    });

    it('applies the configured maximum', function () {
        config(['tablekit.multi_sort.max' => 2]);

        $reloaded = reloadPhase3(['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => 'amount:asc,reference:desc,status:asc']);

        expect(sortColumnsOf($reloaded))->toBe(['amount', 'reference']);
    });

    it('ignores the parameter when multi-sort is off', function () {
        config(['tablekit.multi_sort.enabled' => false]);

        $reloaded = reloadPhase3(['tableSortColumn' => 'amount', 'tableSortDirection' => 'asc', 'tableSorts' => 'amount:asc,reference:desc']);

        expect(sortColumnsOf($reloaded))->toBe(['amount']);
    });

    it('counts as table state, so the startup view is not applied over it', function () {
        phase3Presets(['start' => PresetTableView::make()->default()->defaultSearch('ORD-0')]);

        $reloaded = reloadPhase3(['tableSorts' => '']);

        expect($reloaded->get('tableSearch'))->toBe('')
            ->and($reloaded->get('activeTablePresetKey'))->toBeNull();
    });
});

describe('with a startup view', function () {
    beforeEach(function () {
        phase3Presets([
            'start' => PresetTableView::make()->label('Start')->default()->defaultSort(['customer_name' => 'desc', 'amount' => 'asc', 'reference' => 'asc']),
        ]);
    });

    it('writes what the startup view set and a reload reproduces it', function () {
        $mounted = Livewire::test(ListOrdersPhase3Presets::class);
        $url = browserUrl($mounted);

        expect($url($mounted))->toMatchArray([
            'tableSortColumn' => 'customer_name',
            'tableSortDirection' => 'desc',
            'tableSorts' => 'customer_name:desc,amount:asc,reference:asc',
            'view' => 'preset:start',
        ]);

        $reloaded = reloadPhase3($url($mounted));

        expect(callProtected($reloaded, 'effectiveTableViewSorts'))->toBe(callProtected($mounted, 'effectiveTableViewSorts'))
            ->and(queryReferences($reloaded))->toBe(queryReferences($mounted))
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('keeps a reduced list and a cleared list across reloads', function () {
        $mounted = Livewire::test(ListOrdersPhase3Presets::class);
        $url = browserUrl($mounted);

        $mounted->call('removeTableViewSort', 'reference');
        $reloaded = reloadPhase3($url($mounted));

        expect(sortColumnsOf($reloaded))->toBe(['customer_name', 'amount'])
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();

        $mounted->call('clearTableViewSorts');
        $query = $url($mounted);

        expect($query)->toHaveKey('tableSorts', '');

        $cleared = reloadPhase3($query);

        expect(callProtected($cleared, 'effectiveTableViewSorts'))->toBe([])
            ->and(queryReferences($cleared))->toBe(queryReferences($mounted));
    });

    it('keeps a single sort chosen by a header click after the startup multi-sort', function () {
        $mounted = Livewire::test(ListOrdersPhase3Presets::class);
        $url = browserUrl($mounted);

        $mounted->call('sortTable', 'amount', 'desc');
        $reloaded = reloadPhase3($url($mounted));

        expect(callProtected($reloaded, 'effectiveTableViewSorts'))->toBe([['column' => 'amount', 'direction' => 'desc']])
            ->and(queryReferences($reloaded))->toBe(queryReferences($mounted));
    });
});

it('restores a view from the url and puts the multi-sort of the url on top', function () {
    phase3Presets(['one' => PresetTableView::make()->defaultSort('reference', 'asc')]);

    $mounted = Livewire::test(ListOrdersPhase3Presets::class);
    $url = browserUrl($mounted);

    $mounted->call('applyTablePresetView', 'one')->call('addTableViewSort', 'amount')->call('setTableViewSortDirection', 'amount', 'desc');

    $reloaded = reloadPhase3($url($mounted));

    expect(callProtected($reloaded, 'effectiveTableViewSorts'))->toBe([['column' => 'reference', 'direction' => 'asc'], ['column' => 'amount', 'direction' => 'desc']])
        ->and($reloaded->get('activeTablePresetKey'))->toBe('one')
        ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
});

it('has no sort url state on a table widget', function () {
    $mounted = Livewire::test(OrdersWidget::class);

    expect(array_keys($mounted->effects['url'] ?? []))->not->toContain('tableViewSortsUrl')->not->toContain('tableSortColumn');

    $mounted->call('addTableViewSort', 'reference')->call('addTableViewSort', 'amount');

    expect(array_column(callProtected($mounted, 'effectiveTableViewSorts'), 'column'))->toBe(['reference', 'amount']);
});
