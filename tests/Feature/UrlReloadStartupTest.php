<?php

use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function reloadStartupOrders(array $query): Testable
{
    return Livewire::withQueryParams($query)->test(ListOrdersWithPresets::class);
}

function shownStartupReferences(Testable $component): array
{
    return $component->instance()->getFilteredSortedTableQuery()->pluck('reference')->all();
}

function expectSameStartupScreen(Testable $before, Testable $after): void
{
    expect($after->get('activeTableViewId'))->toBe($before->get('activeTableViewId'))
        ->and($after->get('activeTablePresetKey'))->toBe($before->get('activeTablePresetKey'))
        ->and($after->get('tableFilters'))->toEqual($before->get('tableFilters'))
        ->and($after->get('tableSearch'))->toBe($before->get('tableSearch'))
        ->and($after->get('tableSort') ?: null)->toBe($before->get('tableSort') ?: null)
        ->and(shownStartupReferences($after))->toBe(shownStartupReferences($before))
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
            'filters' => ['status' => ['value' => 'shipped'], 'big' => ['isActive' => 'true']],
            'sort' => 'ordered_at:desc',
            'view' => 'user:'.$this->shipped->id,
        ])->not->toHaveKey('search');

        $mounted->set('tableSearch', 'Alice');
        $query = $url($mounted);

        expect($query)->toHaveKey('search', 'Alice')->toHaveKey('view', 'user:'.$this->shipped->id);

        $reloaded = reloadStartupOrders($query);

        expectSameStartupScreen($mounted, $reloaded);
        expect($reloaded->get('activeTableViewId'))->toBe($this->shipped->id)
            ->and($reloaded->get('tableFilters.status.value'))->toBe('shipped')
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('A: reloading the untouched startup view shows the same screen and stays clean', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $reloaded = reloadStartupOrders(browserUrl($mounted)($mounted));

        expectSameStartupScreen($mounted, $reloaded);
        expect(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('B: keeps a preset with a hidden query and its sort direction, from the startup state', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->call('applyTablePresetView', 'high');
        $query = $url($mounted);

        expect($query)->toMatchArray([
            'sort' => 'amount:desc',
            'view' => 'preset:high',
        ]);

        $reloaded = reloadStartupOrders($query);

        expectSameStartupScreen($mounted, $reloaded);
        expect($reloaded->get('tableSort'))->toBe('amount:desc')
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse()
            ->and(Order::whereIn('reference', shownStartupReferences($reloaded))->where('amount', '<', 500)->count())->toBe(0);
    });

    it('keeps a preset picked on a table that started plain', function () {
        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        expect($url($mounted))->toBe([]);

        $mounted->call('applyTablePresetView', 'high');

        $reloaded = reloadStartupOrders($url($mounted));

        expectSameStartupScreen($mounted, $reloaded);
        expect($reloaded->get('tableSort'))->toBe('amount:desc')->and($reloaded->get('activeTablePresetKey'))->toBe('high');
    });

    it('keeps a preset without a hidden query and one of the user\'s own views', function () {
        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        foreach ([['applyTablePresetView', 'plain'], ['applyTableView', $this->shipped->id]] as [$method, $argument]) {
            $mounted->call($method, $argument);

            expectSameStartupScreen($mounted, reloadStartupOrders($url($mounted)));
        }
    });

    it('keeps a filter the user cleared on top of the startup view', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->set('tableFilters.status.value', null);
        $query = $url($mounted);

        expect($query['filters'])->toBe(['big' => ['isActive' => 'true']])->and($query)->toHaveKey('view');

        $reloaded = reloadStartupOrders($query);

        expectSameStartupScreen($mounted, $reloaded);
        expect($reloaded->get('tableFilters.status.value'))->toBeNull()
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();

        $mounted->set('tableFilters.big.isActive', false);
        $reloadedAgain = reloadStartupOrders($url($mounted));

        expectSameStartupScreen($mounted, $reloadedAgain);
        expect($reloadedAgain->get('activeTableViewId'))->toBe($this->shipped->id);
    });

    it('keeps a cleared sort and a sort changed to the same or another direction than the view', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->call('sortTable', 'amount')->call('sortTable', 'amount');
        expect($mounted->get('tableSort'))->toBe('amount:desc');
        expectSameStartupScreen($mounted, reloadStartupOrders($url($mounted)));

        $mounted->call('sortTable', 'ordered_at', 'desc');
        expect($url($mounted))->toMatchArray(['sort' => 'ordered_at:desc']);
        expectSameStartupScreen($mounted, reloadStartupOrders($url($mounted)));

        $mounted->call('sortTable', 'ordered_at', 'asc');
        expectSameStartupScreen($mounted, reloadStartupOrders($url($mounted)));
        expect(reloadStartupOrders($url($mounted))->get('tableSort'))->toBe('ordered_at:asc');

        $mounted->set('tableSort', null);
        $reloaded = reloadStartupOrders($url($mounted));

        expect($reloaded->get('tableSort'))->toBeEmpty()->and($reloaded->get('activeTableViewId'))->toBe($this->shipped->id);
    });

    it('keeps a sort that equals the startup sort after switching to another view', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->call('applyTablePresetView', 'plain')->call('sortTable', 'ordered_at', 'desc');

        $reloaded = reloadStartupOrders($url($mounted));

        expectSameStartupScreen($mounted, $reloaded);
        expect($reloaded->get('tableSort'))->toBe('ordered_at:desc');
    });

    it('stays on Default after Default is clicked and the page is reloaded', function () {
        ($this->makeStartup)();

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        $mounted->call('applyDefaultTableView');
        $query = $url($mounted);

        expect($query)->toHaveKey('view', '');

        $reloaded = reloadStartupOrders($query);

        expect($reloaded->get('activeTableViewId'))->toBeNull()
            ->and($reloaded->get('tableFilters.status.value'))->toBeNull()
            ->and($reloaded->get('tableSort'))->toBeEmpty()
            ->and(shownStartupReferences($reloaded))->toHaveCount(Order::count());
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
        $reloaded = reloadStartupOrders($url($mounted));

        expectSameStartupScreen($mounted, $reloaded);
        expect($reloaded->get('activeTablePresetKey'))->toBe('dev')->and($reloaded->get('tableSort'))->toBe('amount:asc');
    });

    it('keeps the remembered view the session brings back on a fresh load', function () {
        config(['table-views.persist_active_view_in_session' => true]);

        Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'high');

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);

        expect($mounted->get('activeTablePresetKey'))->toBe('high');

        $mounted->set('tableSearch', 'Alice')->call('sortTable', 'amount', 'desc');
        expectSameStartupScreen($mounted, reloadStartupOrders($url($mounted)));
    });

    it('does not add anything to the url of a table that starts plain', function () {
        $mounted = Livewire::test(ListOrdersWithPresets::class);

        expect(collect($mounted->effects['url'])->where('alwaysShow', true)->all())->toBe([]);
    });

    it('asks the browser to always show only what the startup view changed', function () {
        ($this->makeStartup)();

        $always = collect(Livewire::test(ListOrdersWithPresets::class)->effects['url'])->filter(fn ($entry) => $entry['alwaysShow'])->keys()->sort()->values()->all();

        expect($always)->toBe(['tableFilters', 'tableSort', 'tableViewUrl']);
    });

    it('still ignores hostile view values and applies the url state', function (mixed $value) {
        ($this->makeStartup)();

        $reloaded = reloadStartupOrders(['view' => $value, 'search' => 'Alice']);

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

        reloadStartupOrders(['view' => 'preset:nope'])->assertSet('activeTableViewId', $this->shipped->id);
    });
});
