<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    presets(fn () => [
        'a' => PresetTableView::make()->label('Alpha preset')->favorite(),
        'b' => PresetTableView::make()->label('Beta preset'),
        'hidden' => PresetTableView::make()->label('Hidden preset')->visible(false),
        'default' => PresetTableView::make()->label('Everything preset'),
    ]);

    storeView($this->user->id, [], ['name' => 'Fav view', 'sort' => 1, 'resource' => ListOrdersWithPresets::class]);
    storeView($this->user->id, [], ['name' => 'Plain view', 'sort' => 2, 'is_favorite' => false, 'resource' => ListOrdersWithPresets::class]);
});

function clickCount(string $html, string $method): int
{
    return preg_match_all('/wire:click="[^"]*'.preg_quote($method, '/').'/', $html);
}

function openManager()
{
    return Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager');
}

describe('dropdown', function () {
    it('renders the trigger but not the list until it is opened', function () {
        Livewire::test(ListOrdersWithPresets::class)
            ->assertSee(__('table-views::manager.label'))
            ->assertSee(__('table-views::manager.loading'))
            ->assertDontSee('Plain view')
            ->assertDontSee('Beta preset')
            ->assertDontSee(__('table-views::manager.sections.presets'));
    });

    it('lists favorites, my views and preset views in that order', function () {
        openManager()
            ->assertSeeInOrder([
                __('table-views::manager.sections.favorites'),
                'Alpha preset',
                'Fav view',
                __('table-views::manager.sections.my_views'),
                'Plain view',
                __('table-views::manager.sections.presets'),
                'Beta preset',
            ])
            ->assertDontSee('Hidden preset');
    });

    it('does not list the default key preset in the manager', function () {
        $html = openManager()->html();

        expect(substr_count($html, 'Everything preset'))->toBe(1);
    });

    it('hides empty sections', function () {
        presets(fn () => ['a' => PresetTableView::make()->label('Alpha preset')->favorite()]);

        ListOrdersWithPresets::$presets = fn () => [];
        $component = openManager();

        $component->assertSee(__('table-views::manager.sections.favorites'))
            ->assertSee(__('table-views::manager.sections.my_views'))
            ->assertDontSee(__('table-views::manager.sections.presets'));

        TableView::query()->delete();

        openManager()->assertDontSee(__('table-views::manager.sections.favorites'))->assertSee(__('table-views::manager.empty'));
    });

    it('marks the active view and shows row actions by type', function () {
        $component = openManager()->call('applyTablePresetView', 'a');

        $component->assertSee(__('table-views::manager.active'))->assertSeeHtml('data-active="true"');

        $html = $component->html();

        expect(clickCount($html, 'editTableView'))->toBe(2)
            ->and(clickCount($html, 'deleteTableView'))->toBe(2)
            ->and($html)->not->toContain('moveTableView')
            ->and(clickCount($html, 'toggleTableViewFavorite'))->toBe(4)
            ->and(clickCount($html, 'toggleTableViewDefault'))->toBe(0);
    });

    it('renders drag handles and sortable lists instead of arrow buttons', function () {
        presets(fn () => [
            'a' => PresetTableView::make()->label('Alpha preset')->favorite(),
            'a2' => PresetTableView::make()->label('Alpha two')->favorite(),
            'b' => PresetTableView::make()->label('Beta preset'),
            'b2' => PresetTableView::make()->label('Beta two'),
        ]);

        storeView($this->user->id, [], ['name' => 'Fav two', 'sort' => 3, 'resource' => ListOrdersWithPresets::class]);
        storeView($this->user->id, [], ['name' => 'Plain two', 'sort' => 4, 'is_favorite' => false, 'resource' => ListOrdersWithPresets::class]);

        $html = openManager()->html();

        expect(substr_count($html, "\$call('reorderTableViews'"))->toBe(4)
            ->and($html)->toContain('x-sortable-item="a2"')
            ->and($html)->toContain('x-on:keydown.arrow-up.prevent')
            ->and($html)->toContain('x-on:keydown.arrow-down.prevent')
            ->and($html)->not->toContain('moveTableView');

        foreach (['Alpha preset', 'Alpha two', 'Beta preset', 'Beta two', 'Fav view', 'Fav two', 'Plain view', 'Plain two'] as $label) {
            expect($html)->toContain(__('table-views::reorder.handle', ['name' => $label]));
        }
    });

    it('renders no handle for a section with a single view', function () {
        $html = openManager()->html();

        expect($html)->not->toContain('reorderTableViews')->and($html)->not->toContain('Reorder Alpha preset');
    });

    it('renders no preset handles when presets are not manageable', function () {
        config(['table-views.preset_views.manageable' => false]);

        presets(fn () => [
            'a' => PresetTableView::make()->label('Alpha preset')->favorite(),
            'a2' => PresetTableView::make()->label('Alpha two')->favorite(),
        ]);

        expect(openManager()->html())->not->toContain('x-sortable-item="a2"');
    });

    it('shows default buttons only with managed defaults', function () {
        config(['table-views.managed_default_views' => true]);

        expect(clickCount(openManager()->html(), 'toggleTableViewDefault'))->toBe(4);
    });

    it('hides preset favorite and move buttons when presets are not manageable', function () {
        config(['table-views.preset_views.manageable' => false]);

        $html = openManager()->html();

        expect(clickCount($html, 'toggleTableViewFavorite'))->toBe(2)
            ->and($html)->not->toContain('moveTableView')
            ->and($html)->not->toContain('toggleTableViewFavorite(&#039;preset');
    });

    it('shows the lock icon label for presets only when enabled', function () {
        openManager()->assertSeeHtml('>'.__('table-views::manager.type.preset').'<')->assertSeeHtml('>'.__('table-views::manager.type.user').'<');

        TableViewsPlugin::get()->presetViewLockIcon(false);

        openManager()->assertDontSeeHtml('>'.__('table-views::manager.type.preset').'<')->assertSeeHtml('>'.__('table-views::manager.type.user').'<');
    });

    it('shows the search box above eight views, and follows the config', function () {
        openManager()->assertDontSee(__('table-views::manager.search'));

        foreach (range(1, 7) as $i) {
            storeView($this->user->id, [], ['name' => "Extra {$i}", 'resource' => ListOrdersWithPresets::class]);
        }

        openManager()->assertSee(__('table-views::manager.search'));

        config(['table-views.view_manager.search' => false]);

        openManager()->assertDontSee(__('table-views::manager.search'));

        config(['table-views.view_manager.search' => true]);
        TableView::query()->where('name', 'like', 'Extra%')->delete();

        openManager()->assertSee(__('table-views::manager.search'));
    });

    it('renders arabic labels', function () {
        app()->setLocale('ar');

        openManager()->assertSee(__('table-views::manager.sections.favorites'))->assertSee('المفضلة');
    });
});

describe('position and switches', function () {
    it('puts the manager after the save button by default and before the default button at start', function () {
        Livewire::test(ListOrdersWithPresets::class)->assertSeeInOrder(['Save view', __('table-views::manager.label')]);

        config(['table-views.view_manager.position' => 'start']);

        Livewire::test(ListOrdersWithPresets::class)->assertSeeInOrder([__('table-views::manager.label'), 'Default', 'Save view']);

        config(['table-views.view_manager.position' => 'nonsense']);

        Livewire::test(ListOrdersWithPresets::class)->assertSeeInOrder(['Save view', __('table-views::manager.label')]);
    });

    it('can be positioned and disabled on the plugin', function () {
        TableViewsPlugin::get()->viewManager(position: 'start');

        Livewire::test(ListOrdersWithPresets::class)->assertSeeInOrder([__('table-views::manager.label'), 'Default']);

        TableViewsPlugin::get()->viewManager(false);

        Livewire::test(ListOrdersWithPresets::class)->assertDontSee(__('table-views::manager.label'));
    });

    it('can be disabled in config', function () {
        config(['table-views.view_manager.enabled' => false]);

        Livewire::test(ListOrdersWithPresets::class)->assertDontSee(__('table-views::manager.label'))->assertSee('Save view');
    });
});

describe('slide over', function () {
    it('renders an action instead of the dropdown', function () {
        config(['table-views.view_manager.slide_over' => true]);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSee(__('table-views::manager.label'))
            ->assertDontSee(__('table-views::manager.loading'))
            ->assertDontSeeHtml('fi-table-views-manager');
    });

    it('opens the list in a slide over modal', function () {
        TableViewsPlugin::get()->viewManager(slideOver: true);

        $component = Livewire::test(ListOrdersWithPresets::class)
            ->assertDontSee('Plain view')
            ->mountAction('manageTableViews');

        expect($component->instance()->getMountedAction()->isModalSlideOver())->toBeTrue();

        $component->assertSee(__('table-views::manager.heading'))
            ->assertSee('Plain view')
            ->assertSee('Beta preset')
            ->assertSee(__('table-views::manager.close'))
            ->assertDontSee('Edit table view')
            ->assertDontSee('Delete table view');
    });

    it('opens the edit and delete modals on top of the slide over', function () {
        config(['table-views.view_manager.slide_over' => true]);

        $view = TableView::where('name', 'Plain view')->first();

        Livewire::test(ListOrdersWithPresets::class)
            ->mountAction('manageTableViews')
            ->call('mountAction', 'editTableView', ['viewId' => $view->id])
            ->assertSet('mountedActions', ['manageTableViews', 'editTableView'])
            ->setActionData(['name' => 'Renamed in slide over', 'color' => 'primary'])
            ->callMountedAction()
            ->assertSet('mountedActions', ['manageTableViews'])
            ->assertSee('Renamed in slide over');

        expect($view->fresh()->name)->toBe('Renamed in slide over');
    });
});

describe('slide over delete', function () {
    it('deletes through the nested confirmation modal', function () {
        config(['table-views.view_manager.slide_over' => true]);

        $view = TableView::where('name', 'Plain view')->first();

        Livewire::test(ListOrdersWithPresets::class)
            ->mountAction('manageTableViews')
            ->call('mountAction', 'deleteTableView', ['viewId' => $view->id])
            ->assertSet('mountedActions', ['manageTableViews', 'deleteTableView'])
            ->callMountedAction()
            ->assertSet('mountedActions', ['manageTableViews'])
            ->assertDontSee('Plain view');

        expect(TableView::find($view->id))->toBeNull();
    });
});

describe('queries', function () {
    function preferenceQueryCount(callable $callback): int
    {
        $count = 0;

        DB::listen(function ($query) use (&$count) {
            if (str_contains($query->sql, 'table_view_preferences')) {
                $count++;
            }
        });

        $callback();

        return $count;
    }

    it('does not touch the preferences table when there are no presets and the manager is closed', function () {
        expect(preferenceQueryCount(fn () => Livewire::test(ListOrders::class)->call('$refresh')))->toBe(0);
    });

    it('reads the preferences once per request when presets exist', function () {
        $component = Livewire::test(ListOrdersWithPresets::class);

        expect(preferenceQueryCount(fn () => $component->call('$refresh')))->toBe(1);
    });

    it('does not run the managed default lookup when the feature is off', function () {
        presets(fn () => []);

        expect(preferenceQueryCount(fn () => Livewire::test(ListOrdersWithPresets::class)))->toBe(0);
    });
});
