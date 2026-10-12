<?php

use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function reloadOrders(array $query): Testable
{
    return Livewire::withQueryParams($query)->test(ListOrdersWithPresets::class);
}

function shownReferences(Testable $component): array
{
    return $component->instance()->getFilteredSortedTableQuery()->pluck('reference')->all();
}

function expectSameScreen(Testable $before, Testable $after): void
{
    expect($after->get('activeTableViewId'))->toBe($before->get('activeTableViewId'))
        ->and($after->get('activeTablePresetKey'))->toBe($before->get('activeTablePresetKey'))
        ->and($after->get('tableFilters'))->toEqual($before->get('tableFilters'))
        ->and($after->get('tableSearch'))->toBe($before->get('tableSearch'))
        ->and($after->get('tableSortColumn'))->toBe($before->get('tableSortColumn'))
        ->and($after->get('tableSortDirection'))->toBe($before->get('tableSortDirection'))
        ->and(shownReferences($after))->toBe(shownReferences($before))
        ->and(callProtected($after, 'isActiveTableViewModified'))->toBe(callProtected($before, 'isActiveTableViewModified'));
}

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config(['table-views.managed_default_views' => true]);

    presets(fn () => [
        'high' => PresetTableView::make()
            ->label('High value, unpaid')
            ->favorite()
            ->defaultSort('amount', 'desc')
            ->defaultFilters(['big' => ['isActive' => false]])
            ->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 500)),
        'plain' => PresetTableView::make()->label('Plain')->defaultSort('customer_name', 'asc'),
    ]);

    $this->shipped = storeView($this->user->id, [
        'filters' => ['status' => ['value' => 'shipped'], 'big' => ['isActive' => true]],
        'sort' => ['column' => 'ordered_at', 'direction' => 'desc'],
    ], ['name' => 'Shipped & big', 'resource' => ListOrdersWithPresets::class]);

    $this->makeStartup = fn () => storePreference($this->user->id, 'user', (string) $this->shipped->id, ['is_default' => true]);
});

describe('reload reproduces what the user saw', function () {
    it('A: keeps a search typed on top of the startup view', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        expect($url($mounted))->toMatchArray([
            'tableFilters' => ['status' => ['value' => 'shipped'], 'big' => ['isActive' => 'true']],
            'tableSortColumn' => 'ordered_at',
            'tableSortDirection' => 'desc',
            'view' => 'user:'.$this->shipped->id,
        ])->not->toHaveKey('tableSearch');

        $mounted->set('tableSearch', 'Alice');
        $query = $url($mounted);

        expect($query)->toHaveKey('tableSearch', 'Alice')->toHaveKey('view', 'user:'.$this->shipped->id);

        $reloaded = reloadOrders($query);

        expectSameScreen($mounted, $reloaded);
        expect($reloaded->get('activeTableViewId'))->toBe($this->shipped->id)
            ->and($reloaded->get('tableFilters.status.value'))->toBe('shipped')
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('A: reloading the untouched startup view shows the same screen and stays clean', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $reloaded = reloadOrders(browserUrl($mounted)($mounted));

        expectSameScreen($mounted, $reloaded);
        expect(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('B: keeps a preset with a hidden query and its sort direction, from the startup state', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->call('applyTablePresetView', 'high');
        $query = $url($mounted);

        expect($query)->toMatchArray([
            'tableSortColumn' => 'amount',
            'tableSortDirection' => 'desc',
            'view' => 'preset:high',
        ]);

        $reloaded = reloadOrders($query);

        expectSameScreen($mounted, $reloaded);
        expect($reloaded->get('tableSortDirection'))->toBe('desc')
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse()
            ->and(Order::whereIn('reference', shownReferences($reloaded))->where('amount', '<', 500)->count())->toBe(0);
    });

    it('keeps a preset picked on a table that started plain', function () {
        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        expect($url($mounted))->toBe([]);

        $mounted->call('applyTablePresetView', 'high');

        $reloaded = reloadOrders($url($mounted));

        expectSameScreen($mounted, $reloaded);
        expect($reloaded->get('tableSortDirection'))->toBe('desc')->and($reloaded->get('activeTablePresetKey'))->toBe('high');
    });

    it('keeps a preset without a hidden query and one of the user\'s own views', function () {
        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        foreach ([['applyTablePresetView', 'plain'], ['applyTableView', $this->shipped->id]] as [$method, $argument]) {
            $mounted->call($method, $argument);

            expectSameScreen($mounted, reloadOrders($url($mounted)));
        }
    });

    it('keeps a filter the user cleared on top of the startup view', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->set('tableFilters.status.value', null);
        $query = $url($mounted);

        expect($query['tableFilters'])->toBe(['big' => ['isActive' => 'true']])->and($query)->toHaveKey('view');

        $reloaded = reloadOrders($query);

        expectSameScreen($mounted, $reloaded);
        expect($reloaded->get('tableFilters.status.value'))->toBeNull()
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();

        $mounted->set('tableFilters.big.isActive', false);
        $reloadedAgain = reloadOrders($url($mounted));

        expectSameScreen($mounted, $reloadedAgain);
        expect($reloadedAgain->get('activeTableViewId'))->toBe($this->shipped->id);
    });

    it('keeps a cleared sort and a sort changed to the same or another direction than the view', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->call('sortTable', 'amount')->call('sortTable', 'amount');
        expect($mounted->get('tableSortDirection'))->toBe('desc');
        expectSameScreen($mounted, reloadOrders($url($mounted)));

        $mounted->call('sortTable', 'ordered_at', 'desc');
        expect($url($mounted))->toMatchArray(['tableSortColumn' => 'ordered_at', 'tableSortDirection' => 'desc']);
        expectSameScreen($mounted, reloadOrders($url($mounted)));

        $mounted->call('sortTable', 'ordered_at', 'asc');
        expectSameScreen($mounted, reloadOrders($url($mounted)));
        expect(reloadOrders($url($mounted))->get('tableSortDirection'))->toBe('asc');

        $mounted->set('tableSortColumn', null)->set('tableSortDirection', null);
        $reloaded = reloadOrders($url($mounted));

        expect($reloaded->get('tableSortColumn'))->toBeEmpty()->and($reloaded->get('activeTableViewId'))->toBe($this->shipped->id);
    });

    it('keeps a sort that equals the startup sort after switching to another view', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->call('applyTablePresetView', 'plain')->call('sortTable', 'ordered_at', 'desc');

        $reloaded = reloadOrders($url($mounted));

        expectSameScreen($mounted, $reloaded);
        expect($reloaded->get('tableSortColumn'))->toBe('ordered_at')->and($reloaded->get('tableSortDirection'))->toBe('desc');
    });

    it('stays on Default after Default is clicked and the page is reloaded', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->call('applyDefaultTableView');
        $query = $url($mounted);

        expect($query)->toHaveKey('view', '');

        $reloaded = reloadOrders($query);

        expect($reloaded->get('activeTableViewId'))->toBeNull()
            ->and($reloaded->get('tableFilters.status.value'))->toBeNull()
            ->and($reloaded->get('tableSortColumn'))->toBeEmpty()
            ->and(shownReferences($reloaded))->toHaveCount(Order::count());
    });

    it('stays on Default when a developer default preset exists and Default is clicked', function () {
        presets(fn () => [
            'dev' => PresetTableView::make()->label('Dev')->default()->defaultSort('amount', 'asc'),
            'high' => PresetTableView::make()->label('High')->defaultSort('amount', 'desc')->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 500)),
        ]);

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        expect($mounted->get('activeTablePresetKey'))->toBe('dev');

        $mounted->call('applyTablePresetView', 'high')->call('applyDefaultTableView');
        $reloaded = reloadOrders($url($mounted));

        expectSameScreen($mounted, $reloaded);
        expect($reloaded->get('activeTablePresetKey'))->toBe('dev')->and($reloaded->get('tableSortDirection'))->toBe('asc');
    });

    it('keeps the remembered view the session brings back on a fresh load', function () {
        config(['table-views.persist_active_view_in_session' => true]);

        Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'high');

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        expect($mounted->get('activeTablePresetKey'))->toBe('high');

        $mounted->set('tableSearch', 'Alice')->call('sortTable', 'amount', 'desc');
        expectSameScreen($mounted, reloadOrders($url($mounted)));
    });

    it('does not add anything to the url of a table that starts plain', function () {
        $mounted = Livewire::test(ListOrdersWithPresets::class);

        expect(collect($mounted->effects['url'])->where('alwaysShow', true)->all())->toBe([]);
    });

    it('asks the browser to always show only what the startup view changed', function () {
        ($this->makeStartup)();

        $always = collect(Livewire::test(ListOrdersWithPresets::class)->effects['url'])->filter(fn ($entry) => $entry['alwaysShow'])->keys()->sort()->values()->all();

        expect($always)->toBe(['tableFilters', 'tableSortColumn', 'tableSortDirection', 'tableViewUrl']);
    });

    it('still ignores hostile view values and applies the url state', function (mixed $value) {
        ($this->makeStartup)();

        $reloaded = reloadOrders(['view' => $value, 'tableSearch' => 'Alice']);

        expect($reloaded->get('activeTableViewId'))->toBeNull()
            ->and($reloaded->get('activeTablePresetKey'))->toBeNull()
            ->and($reloaded->get('tableViewUrl'))->toBeNull()
            ->and($reloaded->get('tableSearch'))->toBe('Alice')
            ->and($reloaded->get('tableFilters.status.value'))->toBeNull();
    })->with([
        'hidden-looking preset' => 'preset:nope',
        'other user view' => 'user:99999',
        'sql' => 'user:1 OR 1=1',
        'null byte' => "preset:\0",
        'long' => 'preset:'.str_repeat('a', 500),
        'array' => [['preset:high']],
    ]);

    it('applies the startup view when the view in the url is invalid and nothing else is in the url', function () {
        ($this->makeStartup)();

        reloadOrders(['view' => 'preset:nope'])->assertSet('activeTableViewId', $this->shipped->id);
    });
});
