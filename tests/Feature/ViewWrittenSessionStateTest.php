<?php

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrdersPersistent;
use TableKit\Tests\Fixtures\Pages\ListOrdersReorderableColumnsPresets;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config(['tablekit.managed_default_views' => true]);

    $this->viewA = storeView($this->user->id, ['toggledColumns' => ['notes' => true, 'ordered_at' => false]], ['name' => 'A', 'resource' => ListOrdersWithPresets::class]);
    $this->viewB = storeView($this->user->id, ['toggledColumns' => ['notes' => false, 'ordered_at' => true]], ['name' => 'B', 'resource' => ListOrdersWithPresets::class]);

    $this->setDefault = function ($view, string $page = ListOrdersWithPresets::class) {
        DB::table('tablekit_preferences')->delete();
        storePreference($this->user->id, 'user', (string) $view->id, ['is_default' => true, 'resource' => $page]);
    };
});

describe('columns written by a view are not the users change', function () {
    it('applies the new managed default\'s columns on the next visit and does not call it modified', function () {
        ($this->setDefault)($this->viewA);

        $first = Livewire::test(ListOrdersWithPresets::class);

        expect($first->get('activeTableViewId'))->toBe($this->viewA->id)
            ->and(toggles($first))->toBe(['notes' => true, 'ordered_at' => false]);

        ($this->setDefault)($this->viewB);

        $second = Livewire::test(ListOrdersWithPresets::class);

        expect($second->get('activeTableViewId'))->toBe($this->viewB->id)
            ->and(toggles($second))->toBe(['notes' => false, 'ordered_at' => true])
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the startup view\'s columns after the user clicked another view', function () {
        presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultToggledColumns(['ordered_at'])]);

        $first = Livewire::test(ListOrdersWithPresets::class)->call('applyTableView', $this->viewA->id);

        expect(toggles($first))->toBe(['notes' => true, 'ordered_at' => false]);

        $second = Livewire::test(ListOrdersWithPresets::class);

        expect($second->get('activeTablePresetKey'))->toBe('dev')
            ->and(toggles($second))->toBe(['notes' => false, 'ordered_at' => true])
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('keeps a toggle the user made after a view over every later reload', function () {
        ($this->setDefault)($this->viewA);

        $first = Livewire::test(ListOrdersWithPresets::class);
        setToggles($first, ['notes' => true, 'ordered_at' => true]);

        foreach ([1, 2, 3] as $visit) {
            $again = Livewire::test(ListOrdersWithPresets::class);

            expect(toggles($again))->toBe(['notes' => true, 'ordered_at' => true], "visit {$visit}");
        }

        ($this->setDefault)($this->viewB);

        expect(toggles(Livewire::test(ListOrdersWithPresets::class)))->toBe(['notes' => true, 'ordered_at' => true]);
    });

    it('keeps the users own columns over a preset that preserves columns', function () {
        presets(fn () => ['keep' => PresetTableView::make()->label('Keep')->favorite()->defaultSearch('ORD')->preserveToggledColumns()]);

        $first = Livewire::test(ListOrdersWithPresets::class)->call('applyTableView', $this->viewA->id);
        setToggles($first, ['notes' => true, 'ordered_at' => true]);
        $first->call('applyTablePresetView', 'keep');

        ($this->setDefault)($this->viewB);

        expect(toggles(Livewire::test(ListOrdersWithPresets::class)))->toBe(['notes' => true, 'ordered_at' => true]);
    });

    it('does not count a reordered column list as a toggle change', function () {
        $first = Livewire::test(ListOrdersReorderableColumnsPresets::class);
        $first->call('applyTableColumnManager', array_reverse($first->get('tableColumns')));

        presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultToggledColumns(['notes'])]);

        $again = Livewire::test(ListOrdersReorderableColumnsPresets::class);

        expect($again->get('activeTablePresetKey'))->toBe('dev')
            ->and(toggles($again))->toBe(['notes' => true, 'amount' => false, 'status' => false])
            ->and(array_column($again->get('tableColumns'), 'name'))->toBe(['notes', 'amount', 'status', 'reference']);
    });

    it('still keeps a real toggle change on a reordered list', function () {
        $first = Livewire::test(ListOrdersReorderableColumnsPresets::class);
        $first->call('applyTableColumnManager', array_reverse($first->get('tableColumns')));
        setToggles($first, ['notes' => false, 'amount' => false, 'status' => true]);

        presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultToggledColumns(['notes'])]);

        $again = Livewire::test(ListOrdersReorderableColumnsPresets::class);

        expect(toggles($again))->toBe(['notes' => false, 'amount' => false, 'status' => true]);
    });
});

describe('a sort written by a view is not the users change', function () {
    beforeEach(function () {
        $this->sortA = storeView($this->user->id, ['sort' => ['column' => 'reference', 'direction' => 'asc']], ['name' => 'SortA', 'resource' => ListOrdersPersistent::class]);
        $this->sortB = storeView($this->user->id, ['sort' => ['column' => 'amount', 'direction' => 'desc']], ['name' => 'SortB', 'resource' => ListOrdersPersistent::class]);
    });

    it('applies the new managed default\'s sort on the next visit', function () {
        ($this->setDefault)($this->sortA, ListOrdersPersistent::class);

        expect(Livewire::test(ListOrdersPersistent::class)->get('tableSort'))->toBe('reference:asc');

        ($this->setDefault)($this->sortB, ListOrdersPersistent::class);

        $second = Livewire::test(ListOrdersPersistent::class);

        expect($second->get('activeTableViewId'))->toBe($this->sortB->id)
            ->and($second->get('tableSort'))->toBe('amount:desc')
            ->and(callProtected($second, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('keeps a sort the user chose over every later reload', function () {
        ($this->setDefault)($this->sortA, ListOrdersPersistent::class);

        Livewire::test(ListOrdersPersistent::class)->call('sortTable', 'status', 'desc');

        foreach ([1, 2] as $visit) {
            expect(Livewire::test(ListOrdersPersistent::class)->get('tableSort'))->toBe('status:desc', "visit {$visit}");
        }
    });
});
