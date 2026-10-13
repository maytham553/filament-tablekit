<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use TableKit\Models\TablePreference;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config(['tablekit.managed_default_views' => true]);

    $this->view = storeView($this->user->id, ['search' => 'one'], ['name' => 'Only view']);
});

function openManagerIn(string $mode, string $class = ListOrders::class)
{
    config(['tablekit.view_manager.slide_over' => $mode === 'slide-over']);

    $component = Livewire::test($class);

    return $mode === 'slide-over'
        ? $component->mountAction('manageTableViews')
        : $component->call('openTableViewsManager');
}

function defaultRows(): int
{
    return TablePreference::query()->where('is_default', true)->count();
}

describe('managed defaults without presets', function () {
    it('sets and removes the default in every manager mode', function (string $mode) {
        $component = openManagerIn($mode)
            ->assertSee(__('tablekit::manager.actions.default'))
            ->assertDontSee(__('tablekit::manager.actions.remove_default'));

        $component->call('toggleTableViewDefault', 'user', (string) $this->view->id)
            ->assertSee(__('tablekit::manager.actions.remove_default'));

        expect(defaultRows())->toBe(1);

        $component->call('toggleTableViewDefault', 'user', (string) $this->view->id)
            ->assertDontSee(__('tablekit::manager.actions.remove_default'));

        expect(defaultRows())->toBe(0);

        $component->call('toggleTableViewDefault', 'user', (string) $this->view->id);

        expect(defaultRows())->toBe(1);
    })->with(['dropdown', 'slide-over']);

    it('still sets and clears the default when the manager list was never loaded', function (string $mode) {
        config(['tablekit.view_manager.slide_over' => $mode === 'slide-over']);

        $component = Livewire::test(ListOrders::class);

        $component->call('toggleTableViewDefault', 'user', (string) $this->view->id);
        expect(defaultRows())->toBe(1);

        $component->call('toggleTableViewDefault', 'user', (string) $this->view->id);
        expect(defaultRows())->toBe(0);
    })->with(['dropdown', 'slide-over']);

    it('shows the marker of an existing default when the slide over opens', function () {
        storePreference($this->user->id, 'user', (string) $this->view->id, ['is_default' => true, 'resource' => ListOrders::class]);

        openManagerIn('slide-over')->assertSee(__('tablekit::manager.actions.remove_default'));
    });

    it('does not read preferences while the slide over is closed', function () {
        config(['tablekit.view_manager.slide_over' => true]);

        $component = Livewire::test(ListOrders::class);

        $count = 0;
        DB::listen(function ($query) use (&$count) {
            if (str_contains($query->sql, 'tablekit_preferences')) {
                $count++;
            }
        });

        $component->call('$refresh');

        expect($count)->toBe(0);
    });
});

describe('opening the manager', function () {
    beforeEach(function () {
        presets(fn () => [
            'shipped' => PresetTableView::make()->label('Shipped')->favorite()->defaultFilters(['status' => ['value' => 'shipped']]),
            'big' => PresetTableView::make()->label('Big')->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 500)),
            'dev' => PresetTableView::make()->label('Dev default')->default()->defaultSearch('ORD-09'),
        ]);

        storeView($this->user->id, ['search' => 'two'], ['name' => 'Second', 'resource' => ListOrdersWithPresets::class]);
        storePreference($this->user->id, 'preset', 'big', ['is_default' => true]);
    });

    function tableState($component): array
    {
        $instance = $component->instance();

        return [
            'filters' => $instance->tableFilters,
            'search' => $instance->tableSearch,
            'sort' => [$instance->tableSortColumn, $instance->tableSortDirection],
            'columns' => $instance->toggledTableColumns,
            'preset' => $instance->activeTablePresetKey,
            'view' => $instance->activeTableViewId,
            'hash' => $instance->activeTableViewHash,
            'session' => session()->all(),
        ];
    }

    it('never applies or changes a view, in either mode', function (string $mode, bool $persist) {
        config(['tablekit.persist_active_view_in_session' => $persist]);

        $fresh = Livewire::test(ListOrdersWithPresets::class);
        $before = tableState($fresh);

        config(['tablekit.view_manager.slide_over' => $mode === 'slide-over']);

        $component = Livewire::test(ListOrdersWithPresets::class);

        $component = $mode === 'slide-over'
            ? $component->mountAction('manageTableViews')
            : $component->call('openTableViewsManager')->call('openTableViewsManager');

        $component->call('$refresh')->assertSee('Shipped')->assertSee('Second');

        expect(tableState($component))->toBe($before);
    })->with(['dropdown', 'slide-over'])->with([false, true]);

    it('only renders view buttons as explicit clicks, and the trigger only loads the list', function () {
        config(['tablekit.view_manager.slide_over' => false]);

        $component = Livewire::test(ListOrdersWithPresets::class);
        $closed = $component->html();

        expect($closed)->toContain('$wire.tableViewsManagerLoaded || $wire.openTableViewsManager()');

        $html = $component->call('openTableViewsManager')->html();
        $rows = substr_count($html, 'data-type="');

        preg_match_all('/wire:click="(applyTableView|applyTablePresetView)\(/', $html, $applies);

        // one per bar button and one per manager row, nothing else can apply a view
        expect(count($applies[0]))->toBe($rows + substr_count($html, 'data-preset="true"') + substr_count($html, 'wire:key="table-view-'));
    });
});

describe('row actions that open a modal', function () {
    it('closes the dropdown first', function () {
        $html = openManagerIn('dropdown')->html();

        preg_match_all('/<button[^>]*mountAction\(\'(editTableView|deleteTableView)\'[^>]*>/', $html, $matches);

        expect($matches[0])->toHaveCount(2);

        foreach ($matches[0] as $button) {
            expect($button)->toContain('close($event)');
        }
    });

    it('adds nothing to the slide over rows', function () {
        $html = openManagerIn('slide-over')->html();

        preg_match_all('/<button[^>]*mountAction\(\'(editTableView|deleteTableView)\'[^>]*>/', $html, $matches);

        expect($matches[0])->toHaveCount(2);

        foreach ($matches[0] as $button) {
            expect($button)->not->toContain('close(');
        }
    });
});
