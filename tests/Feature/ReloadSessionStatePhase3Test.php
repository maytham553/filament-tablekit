<?php

use Elite\TableViews\Models\TablePreference;
use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Columns;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3ColumnsPersistent;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3ColumnsPresets;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function reloadColumnsWithUrl(Testable $mounted, Closure $url, string $page = ListOrdersPhase3Columns::class): Testable
{
    return Livewire::withQueryParams($url($mounted))->test($page);
}

function moveAmountUp(Testable $component): Testable
{
    $order = managerOrder($component);
    $index = array_search('amount', $order, true);

    [$order[$index - 1], $order[$index]] = [$order[$index], $order[$index - 1]];

    return reorderColumns($component, $order);
}

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config(['table-views.quick_filters.enabled' => true]);

    $this->view = storeView($this->user->id, [
        'filters' => ['status' => ['value' => 'shipped']],
        'sort' => ['column' => 'amount', 'direction' => 'desc'],
    ], ['name' => 'Shipped', 'resource' => ListOrdersPhase3Columns::class]);

    $this->richView = storeView($this->user->id, [
        'columnOrder' => ['plain', 'reference'],
        'toggledColumns' => ['notes' => true, 'ordered_at' => false],
        'favoriteFilters' => ['multi'],
    ], ['name' => 'Rich', 'resource' => ListOrdersPhase3Columns::class]);

    $this->makeDefault = fn (int $id, string $page = ListOrdersPhase3Columns::class) => TablePreference::create([
        'user_id' => $this->user->id,
        'resource' => $page,
        'view_type' => 'user',
        'view_key' => (string) $id,
        'is_default' => true,
    ]);
});

describe('a url reload keeps what the session persisted on top of the view', function () {
    it('keeps the column order', function () {
        $mounted = Livewire::test(ListOrdersPhase3Columns::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->view->id);
        moveAmountUp($mounted);

        expect(array_slice(headerOrder($mounted->html()), 0, 4))->toBe(['reference', 'customer-name', 'amount', 'status'])
            ->and(callProtected($mounted, 'isActiveTableViewModified'))->toBeTrue();

        $reloaded = reloadColumnsWithUrl($mounted, $url);

        expect($reloaded->get('activeTableViewId'))->toBe($this->view->id)
            ->and(managerOrder($reloaded))->toBe(managerOrder($mounted))
            ->and(headerOrder($reloaded->html()))->toBe(headerOrder($mounted->html()))
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();

        $again = reloadColumnsWithUrl($mounted, $url);

        expect(managerOrder($again))->toBe(managerOrder($mounted))
            ->and(callProtected($again, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps the toggled columns', function () {
        $mounted = Livewire::test(ListOrdersPhase3Columns::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->view->id);
        setToggles($mounted, ['notes' => true, 'ordered_at' => false]);

        $reloaded = reloadColumnsWithUrl($mounted, $url);

        expect(toggles($reloaded))->toEqual(toggles($mounted))
            ->and(headerOrder($reloaded->html()))->toBe(headerOrder($mounted->html()))
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps the pinned filters', function () {
        $mounted = Livewire::test(ListOrdersPhase3Columns::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->view->id)->call('togglePinnedTableViewFilter', 'big')->call('togglePinnedTableViewFilter', 'status');

        $reloaded = reloadColumnsWithUrl($mounted, $url);

        expect($reloaded->get('tableViewFavoriteFilters'))->toBe(['big'])
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps the extra sorts when the sort is persisted in the session', function () {
        $view = storeView($this->user->id, ['sort' => ['column' => 'amount', 'direction' => 'desc']], ['resource' => ListOrdersPhase3ColumnsPersistent::class]);

        $mounted = Livewire::test(ListOrdersPhase3ColumnsPersistent::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $view->id)->call('addTableViewSort', 'customer_name');

        $reloaded = reloadColumnsWithUrl($mounted, $url, ListOrdersPhase3ColumnsPersistent::class);

        expect($reloaded->get('tableViewSorts'))->toBe($mounted->get('tableViewSorts'))->toHaveCount(2)
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('uses the view\'s own columns, order and pins when the session stored none', function () {
        $mounted = Livewire::test(ListOrdersPhase3Columns::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->richView->id);

        session()->flush();
        $this->actingAs($this->user);

        $reloaded = reloadColumnsWithUrl($mounted, $url);

        expect(array_slice(managerOrder($reloaded), 0, 2))->toBe(['plain', 'reference'])
            ->and(toggles($reloaded)['notes'])->toBeTrue()
            ->and(toggles($reloaded)['ordered_at'])->toBeFalse()
            ->and($reloaded->get('tableViewFavoriteFilters'))->toBe(['multi'])
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse();
    });
});

describe('a fresh load that applies a view by itself keeps the session state', function () {
    it('keeps order, toggles and pins over the managed default view', function () {
        ($this->makeDefault)($this->richView->id);
        config(['table-views.managed_default_views' => true]);

        $first = Livewire::test(ListOrdersPhase3Columns::class);

        expect(array_slice(managerOrder($first), 0, 2))->toBe(['plain', 'reference']);

        moveAmountUp($first);
        setToggles($first, ['notes' => false]);
        $first->call('togglePinnedTableViewFilter', 'big');

        $again = Livewire::test(ListOrdersPhase3Columns::class);

        expect($again->get('activeTableViewId'))->toBe($this->richView->id)
            ->and(managerOrder($again))->toBe(managerOrder($first))
            ->and(toggles($again))->toEqual(toggles($first))
            ->and($again->get('tableViewFavoriteFilters'))->toBe($first->get('tableViewFavoriteFilters'))
            ->and(headerOrder($again->html()))->toBe(headerOrder($first->html()))
            ->and(callProtected($again, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps order, toggles and pins over the remembered view', function () {
        config(['table-views.persist_active_view_in_session' => true]);

        $first = Livewire::test(ListOrdersPhase3Columns::class)->call('applyTableView', $this->richView->id);
        moveAmountUp($first);
        setToggles($first, ['notes' => false]);
        $first->call('togglePinnedTableViewFilter', 'big');

        $again = Livewire::test(ListOrdersPhase3Columns::class);

        expect($again->get('activeTableViewId'))->toBe($this->richView->id)
            ->and(managerOrder($again))->toBe(managerOrder($first))
            ->and(toggles($again))->toEqual(toggles($first))
            ->and($again->get('tableViewFavoriteFilters'))->toBe($first->get('tableViewFavoriteFilters'));
    });

    it('keeps the persisted sorts over the startup view', function () {
        config(['table-views.managed_default_views' => true]);
        $default = storeView($this->user->id, ['sort' => ['column' => 'reference', 'direction' => 'asc']], ['resource' => ListOrdersPhase3ColumnsPersistent::class]);
        ($this->makeDefault)($default->id, ListOrdersPhase3ColumnsPersistent::class);

        $first = Livewire::test(ListOrdersPhase3ColumnsPersistent::class);

        expect(sortColumnOf($first))->toBe('reference');

        $first->call('sortTable', 'amount', 'desc')->call('addTableViewSort', 'customer_name');

        $again = Livewire::test(ListOrdersPhase3ColumnsPersistent::class);

        expect($again->get('activeTableViewId'))->toBe($default->id)
            ->and(sortColumnOf($again))->toBe('amount')
            ->and($again->get('tableViewSorts'))->toBe($first->get('tableViewSorts'))->toHaveCount(2);
    });

    it('applies the startup view in full when the session stored nothing', function () {
        ($this->makeDefault)($this->richView->id);
        config(['table-views.managed_default_views' => true]);

        $mounted = Livewire::test(ListOrdersPhase3Columns::class);

        expect(array_slice(managerOrder($mounted), 0, 2))->toBe(['plain', 'reference'])
            ->and(toggles($mounted)['notes'])->toBeTrue()
            ->and($mounted->get('tableViewFavoriteFilters'))->toBe(['multi'])
            ->and(callProtected($mounted, 'isActiveTableViewModified'))->toBeFalse();
    });
});

describe('what a view wrote is not the user\'s change', function () {
    it('applies the next managed default\'s order, pins and sorts instead of the previous view\'s', function () {
        config(['table-views.managed_default_views' => true]);

        $a = storeView($this->user->id, ['columnOrder' => ['plain', 'reference'], 'favoriteFilters' => ['multi']], ['name' => 'A', 'resource' => ListOrdersPhase3ColumnsPersistent::class]);
        $b = storeView($this->user->id, ['columnOrder' => ['notes', 'amount'], 'favoriteFilters' => ['big'], 'sort' => ['column' => 'amount', 'direction' => 'desc']], ['name' => 'B', 'resource' => ListOrdersPhase3ColumnsPersistent::class]);

        ($this->makeDefault)($a->id, ListOrdersPhase3ColumnsPersistent::class);

        $first = Livewire::test(ListOrdersPhase3ColumnsPersistent::class);

        expect(array_slice(managerOrder($first), 0, 2))->toBe(['plain', 'reference']);

        TablePreference::query()->delete();
        ($this->makeDefault)($b->id, ListOrdersPhase3ColumnsPersistent::class);

        $second = Livewire::test(ListOrdersPhase3ColumnsPersistent::class);

        expect($second->get('activeTableViewId'))->toBe($b->id)
            ->and(array_slice(managerOrder($second), 0, 2))->toBe(['notes', 'amount'])
            ->and($second->get('tableViewFavoriteFilters'))->toBe(['big'])
            ->and($second->get('tableSort'))->toBe('amount:desc')
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the startup preset\'s order after the user clicked a view with another order', function () {
        phase3Presets(['dev' => PresetTableView::make()->label('Dev')->default()->defaultColumns(['amount', 'reference'])]);

        $view = storeView($this->user->id, ['columnOrder' => ['plain', 'reference']], ['name' => 'Plain first', 'resource' => ListOrdersPhase3ColumnsPresets::class]);

        $first = Livewire::test(ListOrdersPhase3ColumnsPresets::class)->call('applyTableView', $view->id);

        expect(array_slice(managerOrder($first), 0, 2))->toBe(['plain', 'reference']);

        $second = Livewire::test(ListOrdersPhase3ColumnsPresets::class);

        expect($second->get('activeTablePresetKey'))->toBe('dev')
            ->and(array_slice(managerOrder($second), 0, 2))->toBe(['amount', 'reference'])
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('keeps an order the user made over the startup preset\'s, on every later load', function () {
        phase3Presets(['dev' => PresetTableView::make()->label('Dev')->default()->defaultColumns(['amount', 'reference'])]);

        $first = Livewire::test(ListOrdersPhase3ColumnsPresets::class);
        reorderColumns($first, ['reference', 'amount', ...array_values(array_diff(managerOrder($first), ['reference', 'amount']))]);

        foreach ([1, 2, 3] as $visit) {
            expect(array_slice(managerOrder(Livewire::test(ListOrdersPhase3ColumnsPresets::class)), 0, 2))->toBe(['reference', 'amount'], "visit {$visit}");
        }
    });
});

it('still replaces everything when a view is applied by click', function () {
    $component = Livewire::test(ListOrdersPhase3Columns::class);
    moveAmountUp($component);
    $component->call('togglePinnedTableViewFilter', 'big');
    setToggles($component, ['team.name' => false]);
    $component->call('applyTableView', $this->richView->id);

    expect(array_slice(managerOrder($component), 0, 2))->toBe(['plain', 'reference'])
        ->and($component->get('tableViewFavoriteFilters'))->toBe(['multi'])
        ->and(toggles($component)['notes'])->toBeTrue()
        ->and(toggles($component)['team.name'])->toBeTrue()
        ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
});
