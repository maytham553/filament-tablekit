<?php

use Elite\TableViews\Models\TablePreference;
use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;
use Elite\TableViews\Tests\Fixtures\Team;
use Elite\TableViews\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config(['table-views.managed_default_views' => true]);

    presets(fn () => [
        'shipped' => PresetTableView::make()->label('Shipped')->favorite()->defaultFilters(['status' => ['value' => 'shipped']]),
        'big' => PresetTableView::make()->label('Big')->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 500)),
        'dev' => PresetTableView::make()->label('Dev default')->default()->defaultSearch('ORD-09'),
    ]);

    $this->view = storeView($this->user->id, ['filters' => ['status' => ['value' => 'paid']]], ['name' => 'Paid', 'resource' => ListOrdersWithPresets::class]);
    $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'x']);
});

function makeDefault(string $type, string|int $key, array $attributes = []): TablePreference
{
    return storePreference(test()->user->id, $type, (string) $key, ['is_default' => true, ...$attributes]);
}

it('applies the users default user view on first load', function () {
    makeDefault('user', $this->view->id);

    Livewire::test(ListOrdersWithPresets::class)
        ->assertSet('activeTableViewId', $this->view->id)
        ->assertSet('activeTablePresetKey', null)
        ->assertSet('tableFilters.status.value', 'paid')
        ->assertCountTableRecords(Order::where('status', 'paid')->count());
});

it('applies the users default preset, including its hidden query', function () {
    makeDefault('preset', 'big');

    Livewire::test(ListOrdersWithPresets::class)
        ->assertSet('activeTablePresetKey', 'big')
        ->assertCountTableRecords(Order::where('amount', '>=', 500)->count());
});

it('wins over the developer default preset', function () {
    makeDefault('user', $this->view->id);

    Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTableViewId', $this->view->id)->assertSet('tableSearch', '');
});

it('only applies on the first load, not on later requests', function () {
    makeDefault('user', $this->view->id);

    Livewire::test(ListOrdersWithPresets::class)
        ->filterTable('status', 'pending')
        ->searchTable('ORD-1')
        ->assertSet('tableFilters.status.value', 'pending')
        ->assertSet('tableSearch', 'ORD-1');
});

it('is skipped when the url has table state', function () {
    makeDefault('user', $this->view->id);

    Livewire::withQueryParams(['tableSearch' => 'ORD-05'])
        ->test(ListOrdersWithPresets::class)
        ->assertSet('activeTableViewId', null)
        ->assertSet('tableSearch', 'ORD-05');
});

it('is ignored while the feature is off, the developer default still applies', function () {
    config(['table-views.managed_default_views' => false]);
    makeDefault('user', $this->view->id);

    Livewire::test(ListOrdersWithPresets::class)
        ->assertSet('activeTableViewId', null)
        ->assertSet('activeTablePresetKey', 'dev');
});

it('can be switched on with the plugin', function () {
    config(['table-views.managed_default_views' => false]);
    TableViewsPlugin::get()->managedDefaultViews();
    makeDefault('user', $this->view->id);

    Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTableViewId', $this->view->id);
});

describe('fallbacks', function () {
    it('falls back to the developer default when the default view was deleted', function () {
        makeDefault('user', $this->view->id);

        DB::table('table_views')->where('id', $this->view->id)->delete();

        expect(TablePreference::query()->where('is_default', true)->count())->toBe(1);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTableViewId', null)
            ->assertSet('activeTablePresetKey', 'dev');
    });

    it('falls back when the default preset was removed from the code', function () {
        makeDefault('preset', 'gone');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'dev');
    });

    it('falls back when the default preset became hidden', function () {
        presets(fn () => [
            'shipped' => PresetTableView::make()->visible(false)->defaultSearch('x'),
            'dev' => PresetTableView::make()->default()->defaultSearch('ORD-09'),
        ]);
        makeDefault('preset', 'shipped');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'dev')->assertSet('tableSearch', 'ORD-09');
    });

    it('falls back to the filament defaults when nothing else applies', function () {
        presets(fn () => ['shipped' => PresetTableView::make()]);
        makeDefault('preset', 'gone');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', null)->assertSet('activeTableViewId', null);
    });

    it('ignores a default marked hidden, a default for the default key and a malformed default', function (array $attributes) {
        presets(fn () => [
            'default' => PresetTableView::make()->defaultSearch('ORD-01'),
            'dev' => PresetTableView::make()->default()->defaultSearch('ORD-09'),
        ]);
        makeDefault('preset', 'default', $attributes);

        $component = Livewire::test(ListOrdersWithPresets::class)->assertSuccessful();

        expect($component->get('activeTablePresetKey'))->toBe('dev');
    })->with([
        'default key' => [[]],
        'hidden row' => [['is_hidden' => true]],
    ]);

    it('ignores a row with an unknown type', function () {
        makeDefault('weird', 'x');

        Livewire::test(ListOrdersWithPresets::class)->assertSuccessful()->assertSet('activeTablePresetKey', 'dev');
    });

    it('never uses the default of another user, table or tenant', function () {
        TablePreference::create(['user_id' => $this->other->id, 'resource' => ListOrdersWithPresets::class, 'view_type' => 'preset', 'view_key' => 'big', 'is_default' => true]);
        TablePreference::create(['user_id' => $this->user->id, 'resource' => ListOrders::class, 'view_type' => 'preset', 'view_key' => 'big', 'is_default' => true]);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'dev');
    });

    it('never applies another users view even if a row points at it', function () {
        $theirs = storeView($this->other->id, ['search' => 'secret'], ['resource' => ListOrdersWithPresets::class]);
        makeDefault('user', $theirs->id);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTableViewId', null)->assertSet('tableSearch', 'ORD-09');
    });

    it('uses the default of the current tenant only', function () {
        $x = Team::create(['name' => 'X']);
        $y = Team::create(['name' => 'Y']);

        Filament::setCurrentPanel(Filament::getPanel('team'));
        Filament::bootCurrentPanel();
        Filament::setTenant($x);

        $view = storeView($this->user->id, ['search' => 'tenant x'], ['resource' => ListOrdersWithPresets::class, 'tenant_type' => $x->getMorphClass(), 'tenant_id' => $x->id]);
        makeDefault('user', $view->id, ['tenant_type' => $x->getMorphClass(), 'tenant_id' => $x->id]);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('tableSearch', 'tenant x');

        Filament::setTenant($y);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('tableSearch', 'ORD-09');
    });
});

describe('session persistence', function () {
    beforeEach(fn () => config(['table-views.persist_active_view_in_session' => true]));

    it('prefers the view remembered in the session over the managed default', function () {
        makeDefault('user', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'shipped');

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTablePresetKey', 'shipped')
            ->assertSet('activeTableViewId', null);
    });

    it('remembers a preset and restores its query', function () {
        Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTablePresetKey', 'big')
            ->assertCountTableRecords(Order::where('amount', '>=', 500)->count());
    });

    it('does not remember the startup default as a choice, so a later default applies on the next visit', function () {
        makeDefault('user', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTableViewId', $this->view->id);

        TablePreference::query()->delete();

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTableViewId', null)
            ->assertSet('activeTablePresetKey', 'dev');
    });

    it('does not remember a managed default preset as a choice either', function () {
        makeDefault('preset', 'shipped');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'shipped');

        TablePreference::query()->delete();

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'dev');
    });

    it('does not remember the developer default preset as a choice', function () {
        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'dev');

        presets(fn () => [
            'shipped' => PresetTableView::make()->label('Shipped')->favorite()->default()->defaultFilters(['status' => ['value' => 'shipped']]),
            'dev' => PresetTableView::make()->label('Dev default')->defaultSearch('ORD-09'),
        ]);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'shipped');
    });

    it('falls through to the managed default when the remembered view is stale', function () {
        makeDefault('preset', 'shipped');

        Livewire::test(ListOrdersWithPresets::class)->call('applyTableView', $this->view->id);
        $this->view->delete();

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'shipped');
    });

    it('falls through when the remembered preset is gone', function () {
        Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');

        presets(fn () => ['dev' => PresetTableView::make()->default()->defaultSearch('ORD-09')]);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'dev');
    });

    it('keeps an explicit default button press for the rest of the session', function () {
        presets(fn () => ['shipped' => PresetTableView::make()->favorite()]);
        makeDefault('preset', 'shipped');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'shipped')->call('applyDefaultTableView');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', null)->assertSet('activeTableViewId', null);
    });

    it('lets the default button reapply the developer default and remembers that', function () {
        makeDefault('user', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)->call('applyDefaultTableView')->assertSet('activeTablePresetKey', 'dev');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'dev');
    });

    it('forgets the remembered view on default when managed defaults are off', function () {
        config(['table-views.managed_default_views' => false]);
        presets(fn () => ['shipped' => PresetTableView::make()->favorite()]);

        Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'shipped')->call('applyDefaultTableView');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', null);
    });

    it('applies a default chosen in the manager on the next fresh load, even after the startup default ran', function () {
        $other = storeView($this->user->id, ['filters' => ['status' => ['value' => 'pending']]], ['name' => 'Pending', 'resource' => ListOrdersWithPresets::class]);
        makeDefault('user', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTableViewId', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)
            ->call('toggleTableViewDefault', 'user', (string) $other->id)
            ->assertSet('activeTableViewId', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTableViewId', $other->id)
            ->assertSet('tableFilters.status.value', 'pending');
    });

    it('lets a newly chosen default replace an explicit choice of the session', function () {
        makeDefault('user', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'shipped');
        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'shipped')
            ->call('toggleTableViewDefault', 'preset', 'big');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'big');
    });

    it('falls back to the developer default after the default is removed', function () {
        makeDefault('user', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)->call('toggleTableViewDefault', 'user', (string) $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTableViewId', null)
            ->assertSet('activeTablePresetKey', 'dev');
    });

    it('keeps an explicit pick over a default that was set before the pick', function () {
        makeDefault('user', $this->view->id);

        Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'big');
        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'big');
    });

    describe('none sentinel', function () {
        beforeEach(function () {
            presets(fn () => ['shipped' => PresetTableView::make()->label('Shipped')->favorite()]);
            makeDefault('user', $this->view->id);
        });

        it('stores none when Default is pressed and starts plain on the next visit', function () {
            Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTableViewId', $this->view->id)->call('applyDefaultTableView');

            expect(session('table-views.'.md5(ListOrdersWithPresets::class.'||').'.active'))->toBe('none');

            Livewire::test(ListOrdersWithPresets::class)
                ->assertSet('activeTableViewId', null)
                ->assertSet('activeTablePresetKey', null)
                ->assertSet('tableFilters.status.value', null);
        });

        it('keeps starting plain on every later visit', function () {
            Livewire::test(ListOrdersWithPresets::class)->call('applyDefaultTableView');

            Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTableViewId', null);
            Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTableViewId', null);
        });

        it('is replaced by a view picked afterwards', function () {
            Livewire::test(ListOrdersWithPresets::class)->call('applyDefaultTableView');
            Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'shipped');

            Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', 'shipped');
        });

        it('is dropped when the default changes, so the new default applies', function () {
            $other = storeView($this->user->id, ['search' => 'later'], ['name' => 'Later', 'resource' => ListOrdersWithPresets::class]);

            Livewire::test(ListOrdersWithPresets::class)->call('applyDefaultTableView');
            Livewire::test(ListOrdersWithPresets::class)->call('toggleTableViewDefault', 'user', (string) $other->id);

            Livewire::test(ListOrdersWithPresets::class)
                ->assertSet('activeTableViewId', $other->id)
                ->assertSet('tableSearch', 'later');
        });

        it('lets the url state win over none', function () {
            Livewire::test(ListOrdersWithPresets::class)->call('applyDefaultTableView');

            Livewire::withQueryParams(['tableSearch' => 'ORD-05'])
                ->test(ListOrdersWithPresets::class)
                ->assertSet('tableSearch', 'ORD-05')
                ->assertSet('activeTableViewId', null);
        });

        it('is not written when managed defaults are off', function () {
            config(['table-views.managed_default_views' => false]);

            Livewire::test(ListOrdersWithPresets::class)->call('applyDefaultTableView');

            expect(session()->has('table-views.'.md5(ListOrdersWithPresets::class.'||').'.active'))->toBeFalse();
        });
    });

    it('does not restore remembered garbage', function (mixed $value) {
        $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTableView', $this->view->id);
        $key = 'table-views.'.md5(ListOrdersWithPresets::class.'||').'.active';

        session()->put($key, $value);

        Livewire::test(ListOrdersWithPresets::class)->assertSuccessful()->assertSet('activeTableViewId', null);
    })->with(['letters', 'preset:', 'preset:nope', ['array'], 0, -5, '99999999999999999999']);
});
