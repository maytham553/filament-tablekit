<?php

use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3PersistentPresets;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Presets;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config([
        'table-views.managed_default_views' => true,
        'table-views.persist_active_view_in_session' => false,
        'table-views.quick_filters.enabled' => true,
    ]);

    $this->page = ListOrdersPhase3Presets::class;

    $this->makeView = fn (array $state, ?string $page = null) => storeView($this->user->id, $state, ['resource' => $page ?? $this->page]);

    $this->setDefault = function ($view, ?string $page = null) {
        DB::table('table_view_preferences')->delete();
        storePreference($this->user->id, 'user', (string) $view->id, ['is_default' => true, 'resource' => $page ?? $this->page]);
    };

    $this->visit = fn (?string $page = null) => Livewire::test($page ?? $this->page);

    $this->columnsOf = fn ($component) => [$component->get('toggledTableColumns.notes'), $component->get('toggledTableColumns.ordered_at')];
});

describe('columns written by a view are not the users change', function () {
    beforeEach(function () {
        $this->viewA = ($this->makeView)(['toggledColumns' => ['notes' => true, 'ordered_at' => false]]);
        $this->viewB = ($this->makeView)(['toggledColumns' => ['notes' => false, 'ordered_at' => true]]);
    });

    it('applies the new managed default\'s columns on the next visit and does not call it modified', function () {
        ($this->setDefault)($this->viewA);

        $first = ($this->visit)();

        expect($first->get('activeTableViewId'))->toBe($this->viewA->id)
            ->and(($this->columnsOf)($first))->toBe([true, false]);

        ($this->setDefault)($this->viewB);

        $second = ($this->visit)();

        expect($second->get('activeTableViewId'))->toBe($this->viewB->id)
            ->and(($this->columnsOf)($second))->toBe([false, true])
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the startup view\'s columns after the user clicked another view', function () {
        phase3Presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultToggledColumns(['ordered_at'])]);

        $first = ($this->visit)()->call('applyTableView', $this->viewA->id);

        expect(($this->columnsOf)($first))->toBe([true, false]);

        $second = ($this->visit)();

        expect($second->get('activeTablePresetKey'))->toBe('dev')
            ->and(($this->columnsOf)($second))->toBe([false, true])
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the startup view after the user pressed Default', function () {
        $both = ($this->makeView)(['toggledColumns' => ['notes' => true, 'ordered_at' => true]]);

        ($this->setDefault)($this->viewA);

        ($this->visit)()->call('applyTableView', $both->id)->call('applyDefaultTableView');

        $again = ($this->visit)();

        expect($again->get('activeTableViewId'))->toBe($this->viewA->id)
            ->and(($this->columnsOf)($again))->toBe([true, false]);
    });

    it('keeps a toggle the user made after a view over every later reload', function () {
        ($this->setDefault)($this->viewA);

        $first = ($this->visit)();
        $first->set('toggledTableColumns.notes', true)->set('toggledTableColumns.ordered_at', true);

        foreach ([1, 2, 3] as $visit) {
            expect(($this->columnsOf)(($this->visit)()))->toBe([true, true], "visit {$visit}");
        }

        ($this->setDefault)($this->viewB);

        expect(($this->columnsOf)(($this->visit)()))->toBe([true, true]);
    });

    it('keeps the users own columns over a preset that preserves columns', function () {
        phase3Presets(fn () => ['keep' => PresetTableView::make()->label('Keep')->favorite()->defaultSearch('ORD')->preserveToggledColumns()]);

        $first = ($this->visit)()->call('applyTableView', $this->viewA->id);
        $first->set('toggledTableColumns.notes', true)->set('toggledTableColumns.ordered_at', true)->call('applyTablePresetView', 'keep');

        ($this->setDefault)($this->viewB);

        expect(($this->columnsOf)(($this->visit)()))->toBe([true, true]);
    });

    it('compares toggles as a map, so another key order is not a change', function () {
        ($this->setDefault)($this->viewA);

        ($this->visit)();

        $sessionKey = 'tables.'.md5($this->page).'_toggled_columns';
        $stored = session()->get($sessionKey);

        expect($stored)->not->toBeNull();

        session()->put($sessionKey, array_reverse($stored, true));
        ($this->setDefault)($this->viewB);

        expect(($this->columnsOf)(($this->visit)()))->toBe([false, true]);
    });
});

describe('column order written by a view is not the users change', function () {
    beforeEach(function () {
        $this->orderA = ($this->makeView)(['columnOrder' => ['plain', 'reference']]);
        $this->orderB = ($this->makeView)(['columnOrder' => ['amount', 'reference']]);
    });

    it('applies the new managed default\'s order on the next visit', function () {
        ($this->setDefault)($this->orderA);

        expect(array_slice(($this->visit)()->get('tableViewColumnOrder'), 0, 2))->toBe(['plain', 'reference']);

        ($this->setDefault)($this->orderB);

        $second = ($this->visit)();

        expect(array_slice($second->get('tableViewColumnOrder'), 0, 2))->toBe(['amount', 'reference'])
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the startup preset\'s code order after the user clicked a view with an order', function () {
        phase3Presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultSearch('ORD')]);

        $first = ($this->visit)()->call('applyTableView', $this->orderA->id);

        expect($first->get('tableViewColumnOrder'))->not->toBe([]);

        expect(($this->visit)()->get('tableViewColumnOrder'))->toBe([]);
    });

    it('keeps an order the user made over every later reload', function () {
        ($this->setDefault)($this->orderA);

        $first = ($this->visit)()->call('reorderTableViewColumn', 'amount', 'up');
        $mine = $first->get('tableViewColumnOrder');

        foreach ([1, 2, 3] as $visit) {
            expect(($this->visit)()->get('tableViewColumnOrder'))->toBe($mine, "visit {$visit}");
        }

        ($this->setDefault)($this->orderB);

        expect(($this->visit)()->get('tableViewColumnOrder'))->toBe($mine);
    });
});

describe('pinned filters written by a view are not the users change', function () {
    beforeEach(function () {
        $this->pinsA = ($this->makeView)(['favoriteFilters' => ['multi']]);
        $this->pinsB = ($this->makeView)(['favoriteFilters' => ['big']]);
    });

    it('applies the new managed default\'s pins on the next visit', function () {
        ($this->setDefault)($this->pinsA);

        expect(($this->visit)()->get('tableViewFavoriteFilters'))->toBe(['multi']);

        ($this->setDefault)($this->pinsB);

        $second = ($this->visit)();

        expect($second->get('tableViewFavoriteFilters'))->toBe(['big'])
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the startup preset\'s pins after the user clicked a view with pins', function () {
        phase3Presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultFilters(['status' => ['value' => 'paid']])]);

        ($this->visit)()->call('applyTableView', $this->pinsA->id);

        expect(($this->visit)()->get('tableViewFavoriteFilters'))->toBeNull();
    });

    it('keeps a pin the user changed over every later reload', function () {
        ($this->setDefault)($this->pinsA);

        ($this->visit)()->call('togglePinnedTableViewFilter', 'big');

        foreach ([1, 2, 3] as $visit) {
            expect(($this->visit)()->get('tableViewFavoriteFilters'))->toBe(['multi', 'big'], "visit {$visit}");
        }

        ($this->setDefault)($this->pinsB);

        expect(($this->visit)()->get('tableViewFavoriteFilters'))->toBe(['multi', 'big']);
    });
});

describe('a sort written by a view is not the users change', function () {
    beforeEach(function () {
        $this->page = ListOrdersPhase3PersistentPresets::class;

        $this->sortA = ($this->makeView)(['sort' => ['column' => 'reference', 'direction' => 'asc']]);
        $this->sortB = ($this->makeView)(['sort' => ['column' => 'amount', 'direction' => 'desc']]);
        $this->multiA = ($this->makeView)(['sorts' => [
            ['column' => 'status', 'direction' => 'asc'],
            ['column' => 'reference', 'direction' => 'desc'],
        ]]);
        $this->multiB = ($this->makeView)(['sorts' => [
            ['column' => 'amount', 'direction' => 'desc'],
            ['column' => 'customer_name', 'direction' => 'asc'],
        ]]);
    });

    it('applies the new managed default\'s sort on the next visit', function () {
        ($this->setDefault)($this->sortA);

        expect(($this->visit)()->get('tableSortColumn'))->toBe('reference');

        ($this->setDefault)($this->sortB);

        $second = ($this->visit)();

        expect($second->get('activeTableViewId'))->toBe($this->sortB->id)
            ->and($second->get('tableSortColumn'))->toBe('amount')
            ->and($second->get('tableSortDirection'))->toBe('desc')
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the new managed default\'s extra sorts on the next visit', function () {
        ($this->setDefault)($this->multiA);

        expect(($this->visit)()->get('tableViewSorts'))->toHaveCount(2);

        ($this->setDefault)($this->multiB);

        $second = ($this->visit)();

        expect($second->get('tableViewSorts'))->toBe([
            ['column' => 'amount', 'direction' => 'desc'],
            ['column' => 'customer_name', 'direction' => 'asc'],
        ])->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the startup preset\'s sort after the user clicked a view with extra sorts', function () {
        phase3Presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultSort('amount', 'desc')]);

        ($this->visit)()->call('applyTableView', $this->multiA->id);

        $second = ($this->visit)();

        expect($second->get('tableSortColumn'))->toBe('amount')
            ->and($second->get('tableViewSorts'))->toBe([]);
    });

    it('keeps a sort the user chose over every later reload', function () {
        ($this->setDefault)($this->sortA);

        ($this->visit)()->call('sortTable', 'status', 'desc');

        foreach ([1, 2] as $visit) {
            expect(($this->visit)()->get('tableSortColumn'))->toBe('status', "visit {$visit}");
        }

        ($this->setDefault)($this->sortB);

        expect(($this->visit)()->get('tableSortColumn'))->toBe('status');
    });

    it('keeps an extra sort the user added over every later reload and a new default', function () {
        ($this->setDefault)($this->sortA);

        $first = ($this->visit)()->call('addTableViewSort', 'customer_name');
        $mine = $first->get('tableViewSorts');

        expect($mine)->toHaveCount(2);

        foreach ([1, 2] as $visit) {
            expect(($this->visit)()->get('tableViewSorts'))->toBe($mine, "visit {$visit}");
        }

        ($this->setDefault)($this->sortB);

        expect(($this->visit)()->get('tableViewSorts'))->toBe($mine);
    });

    it('keeps the users own sort over a preset that preserves the sort', function () {
        phase3Presets(fn () => ['keep' => PresetTableView::make()->label('Keep')->favorite()->defaultSearch('ORD')->preserveSort()]);

        $first = ($this->visit)()->call('applyTableView', $this->sortA->id)->call('sortTable', 'status', 'desc');
        $first->call('applyTablePresetView', 'keep');

        ($this->setDefault)($this->sortB);

        expect(($this->visit)()->get('tableSortColumn'))->toBe('status');
    });
});
