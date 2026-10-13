<?php

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use TableKit\Models\TablePreference;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Persistent;
use TableKit\Tests\Fixtures\User;

function reloadWithUrl(Testable $mounted, Closure $url, string $page = ListOrdersPhase3::class): Testable
{
    return Livewire::withQueryParams($url($mounted))->test($page);
}

function startWithoutUrl(string $page = ListOrdersPhase3::class): Testable
{
    return Livewire::test($page);
}

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config(['tablekit.quick_filters.enabled' => true]);

    $this->view = storeView($this->user->id, [
        'filters' => ['status' => ['value' => 'shipped']],
        'sort' => ['column' => 'amount', 'direction' => 'desc'],
    ], ['name' => 'Shipped', 'resource' => ListOrdersPhase3::class]);

    $this->richView = storeView($this->user->id, [
        'columnOrder' => ['plain', 'reference'],
        'toggledColumns' => ['notes' => true, 'ordered_at' => false],
        'favoriteFilters' => ['multi'],
    ], ['name' => 'Rich', 'resource' => ListOrdersPhase3::class]);

    $this->makeDefault = fn (int $id) => TablePreference::create([
        'user_type' => User::class,
        'user_id' => $this->user->id,
        'resource' => ListOrdersPhase3::class,
        'view_type' => 'user',
        'view_key' => (string) $id,
        'is_default' => true,
    ]);
});

describe('a url reload keeps what the session persisted on top of the view', function () {
    it('keeps the column order', function () {
        $mounted = Livewire::test(ListOrdersPhase3::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->view->id)->call('reorderTableViewColumn', 'amount', 'up');

        expect(headerOrder($mounted->html()))->toBe(['reference', 'customer-name', 'amount', 'status', 'team', 'ordered-at', 'plain'])
            ->and(callProtected($mounted, 'isActiveTableViewModified'))->toBeTrue();

        $reloaded = reloadWithUrl($mounted, $url);

        expect($reloaded->get('activeTableViewId'))->toBe($this->view->id)
            ->and($reloaded->get('tableViewColumnOrder'))->toBe($mounted->get('tableViewColumnOrder'))
            ->and(headerOrder($reloaded->html()))->toBe(headerOrder($mounted->html()))
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps the toggled columns', function () {
        $mounted = Livewire::test(ListOrdersPhase3::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->view->id)->set('toggledTableColumns.notes', true)->set('toggledTableColumns.ordered_at', false);

        $reloaded = reloadWithUrl($mounted, $url);

        expect($reloaded->get('toggledTableColumns'))->toEqual($mounted->get('toggledTableColumns'))
            ->and(headerOrder($reloaded->html()))->toBe(headerOrder($mounted->html()))
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps the pinned filters', function () {
        $mounted = Livewire::test(ListOrdersPhase3::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->view->id)->call('togglePinnedTableViewFilter', 'big')->call('togglePinnedTableViewFilter', 'status');

        $reloaded = reloadWithUrl($mounted, $url);

        expect($reloaded->get('tableViewFavoriteFilters'))->toBe(['big'])
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps the extra sorts when the sort is persisted in the session', function () {
        $view = storeView($this->user->id, ['sort' => ['column' => 'amount', 'direction' => 'desc']], ['resource' => ListOrdersPhase3Persistent::class]);

        $mounted = Livewire::test(ListOrdersPhase3Persistent::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $view->id)->call('addTableViewSort', 'customer_name');

        $reloaded = reloadWithUrl($mounted, $url, ListOrdersPhase3Persistent::class);

        expect($reloaded->get('tableViewSorts'))->toBe($mounted->get('tableViewSorts'))->toHaveCount(2)
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('uses the view\'s own columns, order and pins when the session stored none', function () {
        $mounted = Livewire::test(ListOrdersPhase3::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->richView->id);

        session()->flush();
        $this->actingAs($this->user);

        $reloaded = reloadWithUrl($mounted, $url);

        expect(array_slice($reloaded->get('tableViewColumnOrder'), 0, 2))->toBe(['plain', 'reference'])
            ->and($reloaded->get('toggledTableColumns.notes'))->toBeTrue()
            ->and($reloaded->get('toggledTableColumns.ordered_at'))->toBeFalse()
            ->and($reloaded->get('tableViewFavoriteFilters'))->toBe(['multi'])
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse();
    });
});

describe('a fresh load that applies a view by itself keeps the session state', function () {
    it('keeps order, toggles and pins over the managed default view', function () {
        ($this->makeDefault)($this->richView->id);
        config(['tablekit.managed_default_views' => true]);

        $first = startWithoutUrl();

        expect(array_slice($first->get('tableViewColumnOrder'), 0, 2))->toBe(['plain', 'reference']);

        $first->call('reorderTableViewColumn', 'amount', 'up')
            ->set('toggledTableColumns.notes', false)
            ->call('togglePinnedTableViewFilter', 'big');

        $again = startWithoutUrl();

        expect($again->get('activeTableViewId'))->toBe($this->richView->id)
            ->and($again->get('tableViewColumnOrder'))->toBe($first->get('tableViewColumnOrder'))
            ->and($again->get('toggledTableColumns'))->toEqual($first->get('toggledTableColumns'))
            ->and($again->get('tableViewFavoriteFilters'))->toBe($first->get('tableViewFavoriteFilters'))
            ->and(headerOrder($again->html()))->toBe(headerOrder($first->html()))
            ->and(callProtected($again, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps order, toggles and pins over the remembered view', function () {
        config(['tablekit.persist_active_view_in_session' => true]);

        $first = startWithoutUrl()->call('applyTableView', $this->richView->id);
        $first->call('reorderTableViewColumn', 'amount', 'up')
            ->set('toggledTableColumns.notes', false)
            ->call('togglePinnedTableViewFilter', 'big');

        $again = startWithoutUrl();

        expect($again->get('activeTableViewId'))->toBe($this->richView->id)
            ->and($again->get('tableViewColumnOrder'))->toBe($first->get('tableViewColumnOrder'))
            ->and($again->get('toggledTableColumns'))->toEqual($first->get('toggledTableColumns'))
            ->and($again->get('tableViewFavoriteFilters'))->toBe($first->get('tableViewFavoriteFilters'));
    });

    it('keeps the persisted sorts over the startup view', function () {
        config(['tablekit.managed_default_views' => true]);
        $default = storeView($this->user->id, ['sort' => ['column' => 'reference', 'direction' => 'asc']], ['resource' => ListOrdersPhase3Persistent::class]);
        TablePreference::create(['user_type' => User::class, 'user_id' => $this->user->id, 'resource' => ListOrdersPhase3Persistent::class, 'view_type' => 'user', 'view_key' => (string) $default->id, 'is_default' => true]);

        $first = startWithoutUrl(ListOrdersPhase3Persistent::class);

        expect($first->get('tableSortColumn'))->toBe('reference');

        $first->call('sortTable', 'amount', 'desc')->call('addTableViewSort', 'customer_name');

        $again = startWithoutUrl(ListOrdersPhase3Persistent::class);

        expect($again->get('activeTableViewId'))->toBe($default->id)
            ->and($again->get('tableSortColumn'))->toBe('amount')
            ->and($again->get('tableViewSorts'))->toBe($first->get('tableViewSorts'))->toHaveCount(2);
    });

    it('applies the startup view in full when the session stored nothing', function () {
        ($this->makeDefault)($this->richView->id);
        config(['tablekit.managed_default_views' => true]);

        $mounted = startWithoutUrl();

        expect(array_slice($mounted->get('tableViewColumnOrder'), 0, 2))->toBe(['plain', 'reference'])
            ->and($mounted->get('toggledTableColumns.notes'))->toBeTrue()
            ->and($mounted->get('tableViewFavoriteFilters'))->toBe(['multi'])
            ->and(callProtected($mounted, 'isActiveTableViewModified'))->toBeFalse();
    });
});

it('still replaces everything when a view is applied by click', function () {
    $component = Livewire::test(ListOrdersPhase3::class)
        ->call('reorderTableViewColumn', 'amount', 'up')
        ->call('togglePinnedTableViewFilter', 'big')
        ->set('toggledTableColumns.team.name', false)
        ->call('applyTableView', $this->richView->id);

    expect(array_slice($component->get('tableViewColumnOrder'), 0, 2))->toBe(['plain', 'reference'])
        ->and($component->get('tableViewFavoriteFilters'))->toBe(['multi'])
        ->and($component->get('toggledTableColumns.notes'))->toBeTrue()
        ->and($component->get('toggledTableColumns.team.name'))->toBeTrue()
        ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
});
