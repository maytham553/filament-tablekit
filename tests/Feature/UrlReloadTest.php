<?php

use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Illuminate\Support\Arr;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function reloadOrders(array $query): Testable
{
    return Livewire::withQueryParams($query)->test(ListOrders::class);
}

function shownReferences(Testable $component): array
{
    return $component->instance()->getFilteredSortedTableQuery()->pluck('reference')->all();
}

function expectSameScreen(Testable $before, Testable $after): void
{
    expect($after->get('tableFilters'))->toEqual($before->get('tableFilters'))
        ->and($after->get('tableSearch'))->toBe($before->get('tableSearch'))
        ->and($after->get('tableSort') ?: null)->toBe($before->get('tableSort') ?: null)
        ->and($after->get('tableGrouping') ?: null)->toBe($before->get('tableGrouping') ?: null)
        ->and(shownReferences($after))->toBe(shownReferences($before));
}

beforeEach(function () {
    config(['table-views.persist_active_view_in_session' => true]);

    $this->user = $this->signIn();
    $this->seedOrders();

    $this->shipped = storeView($this->user->id, [
        'filters' => ['status' => ['value' => 'shipped'], 'big' => ['isActive' => true]],
        'sort' => ['column' => 'ordered_at', 'direction' => 'desc'],
        'grouping' => ['column' => 'status', 'direction' => 'desc'],
    ], ['name' => 'Shipped & big']);

    Livewire::test(ListOrders::class)->call('applyTableView', $this->shipped->id);
});

describe('reload reproduces what the user saw after a remembered view was applied on load', function () {
    it('writes what the view set to the url, and nothing it left alone', function () {
        $mounted = Livewire::test(ListOrders::class)->assertSet('activeTableViewId', $this->shipped->id);

        $always = collect($mounted->effects['url'])->filter(fn ($entry) => $entry['alwaysShow'])->keys()->sort()->values()->all();

        expect($always)->toBe(['tableFilters', 'tableGrouping', 'tableSort', 'tableViewUrl'])
            ->and(browserUrl($mounted)($mounted))->toMatchArray([
                'filters' => ['status' => ['value' => 'shipped'], 'big' => ['isActive' => 'true']],
                'sort' => 'ordered_at:desc',
                'grouping' => 'status:desc',
                'view' => 'user:'.$this->shipped->id,
            ])->not->toHaveKey('search');
    });

    it('shows the same screen after reloading the untouched view', function () {
        $mounted = Livewire::test(ListOrders::class);

        expectSameScreen($mounted, reloadOrders(browserUrl($mounted)($mounted)));
    });

    it('keeps a search typed on top of the view', function () {
        $mounted = Livewire::test(ListOrders::class);
        $url = browserUrl($mounted);

        $mounted->set('tableSearch', 'ORD-0');
        $query = $url($mounted);

        expect($query)->toHaveKey('search', 'ORD-0');

        $reloaded = reloadOrders($query);

        expectSameScreen($mounted, $reloaded);
        expect($reloaded->get('tableSearch'))->toBe('ORD-0')->and($reloaded->get('tableFilters.status.value'))->toBe('shipped');
    });

    it('keeps a filter the user cleared on top of the view', function () {
        $mounted = Livewire::test(ListOrders::class);
        $url = browserUrl($mounted);

        $mounted->set('tableFilters.status.value', null);
        $reloaded = reloadOrders($url($mounted));

        expectSameScreen($mounted, $reloaded);
        expect($reloaded->get('tableFilters.status.value'))->toBeNull()->and($reloaded->get('tableFilters.big.isActive'))->toBeTrue();
    });

    it('keeps a sort that was cleared, and one changed to a direction the view also has', function () {
        $mounted = Livewire::test(ListOrders::class);
        $url = browserUrl($mounted);

        $mounted->call('sortTable', 'ordered_at', 'asc');
        expectSameScreen($mounted, reloadOrders($url($mounted)));
        expect(reloadOrders($url($mounted))->get('tableSort'))->toBe('ordered_at:asc');

        $mounted->set('tableSort', null);
        $reloaded = reloadOrders($url($mounted));

        expect($reloaded->get('tableSort'))->toBeEmpty();
        expectSameScreen($mounted, $reloaded);
    });

    it('keeps a grouping that was removed', function () {
        $mounted = Livewire::test(ListOrders::class);
        $url = browserUrl($mounted);

        $mounted->set('tableGrouping', null);
        $reloaded = reloadOrders($url($mounted));

        expect($reloaded->get('tableGrouping'))->toBeEmpty();
        expectSameScreen($mounted, $reloaded);
    });

    it('does not apply the remembered view again when the url carries table state and no view marker', function () {
        $mounted = Livewire::test(ListOrders::class);
        $reloaded = reloadOrders(Arr::except(browserUrl($mounted)($mounted), 'view'));

        expect($reloaded->get('activeTableViewId'))->toBeNull();
    });
});

it('does not add anything to the url of a table that starts plain', function () {
    session()->flush();
    $this->actingAs($this->user);

    $mounted = Livewire::test(ListOrders::class);

    expect(collect($mounted->effects['url'])->where('alwaysShow', true)->all())->toBe([])
        ->and(browserUrl($mounted)($mounted))->toBe([]);
});

it('asks the browser to always show only the parts a view touched', function () {
    session()->flush();
    $this->actingAs($this->user);

    $search = storeView($this->user->id, ['search' => 'ORD-01'], ['name' => 'Search only']);

    Livewire::test(ListOrders::class)->call('applyTableView', $search->id);

    $always = collect(Livewire::test(ListOrders::class)->effects['url'])->filter(fn ($entry) => $entry['alwaysShow'])->keys()->all();

    expect($always)->toContain('tableSearch')->not->toContain('tableSort')->not->toContain('tableGrouping')->not->toContain('activeTab');
});

it('reads the filters, search, sort, grouping and tab from the parameters Filament 4 uses', function () {
    $effects = Livewire::test(ListOrders::class)->effects['url'];

    expect(collect($effects)->map(fn ($entry) => $entry['as'])->only(['tableFilters', 'tableSearch', 'tableSort', 'tableGrouping', 'activeTab', 'isTableReordering'])->all())
        ->toEqual([
            'tableFilters' => 'filters',
            'tableSearch' => 'search',
            'tableSort' => 'sort',
            'tableGrouping' => 'grouping',
            'activeTab' => 'tab',
            'isTableReordering' => 'reordering',
        ]);
});

it('lets every table parameter win over the remembered view', function (array $query) {
    reloadOrders($query)->assertSet('activeTableViewId', null);
})->with([
    'filters' => [['filters' => ['status' => ['value' => 'paid']]]],
    'search' => [['search' => 'ORD']],
    'sort' => [['sort' => 'amount:asc']],
    'grouping' => [['grouping' => 'status:asc']],
    'reordering' => [['reordering' => '1']],
]);

it('is not confused by the Filament 3 parameter names', function () {
    reloadOrders(['tableSearch' => 'ORD', 'tableSortColumn' => 'amount'])->assertSet('activeTableViewId', $this->shipped->id);
});

it('applies the url state of a link into an untouched table', function () {
    $component = reloadOrders(['filters' => ['status' => ['value' => 'paid']], 'sort' => 'amount:desc', 'search' => 'ORD-0']);

    expect($component->get('tableFilters.status.value'))->toBe('paid')
        ->and($component->get('tableSort'))->toBe('amount:desc')
        ->and($component->get('tableSearch'))->toBe('ORD-0')
        ->and(shownReferences($component))->toBe(Order::where('status', 'paid')->where('reference', 'like', '%ORD-0%')->orderByDesc('amount')->orderByDesc('id')->pluck('reference')->all());
});
