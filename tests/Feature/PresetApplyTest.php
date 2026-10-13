<?php

use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Presets\PresetTableView;
use TableKit\TableKitPlugin;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithoutViews;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresetsAndDefaults;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function preset(string $label = 'Preset'): PresetTableView
{
    return PresetTableView::make()->label($label)->favorite();
}

function bigOrdersPreset(): PresetTableView
{
    return preset('Big')->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 500));
}

it('applies a preset filter and sort and marks it active', function () {
    presets(fn () => [
        'shipped' => preset('Shipped')
            ->defaultFilters(['status' => ['value' => 'shipped']])
            ->defaultSort('amount', 'desc'),
    ]);

    Livewire::test(ListOrdersWithPresets::class)
        ->assertSee('Shipped')
        ->call('applyTablePresetView', 'shipped')
        ->assertSet('activeTablePresetKey', 'shipped')
        ->assertSet('activeTableViewId', null)
        ->assertSet('tableFilters.status.value', 'shipped')
        ->assertSet('tableSortColumn', 'amount')
        ->assertSet('tableSortDirection', 'desc')
        ->assertSeeHtml('data-active="true"')
        ->assertCountTableRecords(Order::where('status', 'shipped')->count());
});

it('accepts a multi column sort array and uses the first pair', function () {
    presets(fn () => ['p' => preset()->defaultSort(['amount' => 'desc', 'reference' => 'asc'])]);

    Livewire::test(ListOrdersWithPresets::class)
        ->call('applyTablePresetView', 'p')
        ->assertSet('tableSortColumn', 'amount')
        ->assertSet('tableSortDirection', 'desc');
});

it('applies search, column searches, toggled columns and grouping', function () {
    presets(fn () => [
        'search' => preset()->defaultSearch('ORD-01')->defaultColumnSearches(['reference' => 'ORD-0']),
        'columns' => preset()->defaultToggledColumns(['notes']),
        'grouped' => preset()->defaultGrouping('customer_name', 'desc'),
    ]);

    $component = Livewire::test(ListOrdersWithPresets::class)
        ->call('applyTablePresetView', 'search')
        ->assertSet('tableSearch', 'ORD-01')
        ->assertSet('tableColumnSearches.reference', 'ORD-0')
        ->call('applyTablePresetView', 'columns')
        ->assertSet('toggledTableColumns', ['notes' => true, 'ordered_at' => false])
        ->assertSet('tableSearch', '')
        ->call('applyTablePresetView', 'grouped')
        ->assertSet('tableGrouping', 'customer_name')
        ->assertSet('tableGroupingDirection', 'desc')
        ->assertSet('toggledTableColumns', ['notes' => false, 'ordered_at' => true]);

    expect($component->get('activeTablePresetKey'))->toBe('grouped');
});

it('resets the parts a preset does not define to the table defaults', function () {
    presets(fn () => ['filters' => preset()->defaultFilters(['status' => ['value' => 'paid']])]);

    Livewire::test(ListOrdersWithPresets::class)
        ->searchTable('ORD')
        ->sortTable('amount', 'desc')
        ->set('toggledTableColumns.notes', true)
        ->set('tableGrouping', 'status')
        ->call('applyTablePresetView', 'filters')
        ->assertSet('tableSearch', '')
        ->assertSet('tableSortColumn', null)
        ->assertSet('toggledTableColumns', ['notes' => false, 'ordered_at' => true])
        ->assertSet('tableGrouping', null)
        ->assertSet('tableFilters.status.value', 'paid');
});

it('lays preset filters over the filter defaults', function () {
    presets(fn () => ['big' => preset()->defaultFilters(['big' => ['isActive' => true]])]);

    Livewire::test(ListOrdersWithPresetsAndDefaults::class)
        ->assertSet('tableFilters.hide_cancelled.isActive', true)
        ->set('tableFilters.hide_cancelled.isActive', false)
        ->call('applyTablePresetView', 'big')
        ->assertSet('tableFilters.hide_cancelled.isActive', true)
        ->assertSet('tableFilters.big.isActive', true);
});

it('keeps the filter defaults when a preset defines an empty filter list', function () {
    presets(fn () => ['none' => preset()->defaultFilters([])]);

    Livewire::test(ListOrdersWithPresetsAndDefaults::class)
        ->set('tableFilters.hide_cancelled.isActive', false)
        ->call('applyTablePresetView', 'none')
        ->assertSet('tableFilters.hide_cancelled.isActive', true);
});

it('keeps what the preserve variants keep and replaces the rest', function (string $method, array $kept) {
    presets(fn () => [
        'p' => preset()
            ->defaultFilters(['status' => ['value' => 'paid']])
            ->defaultSearch('ORD-09')
            ->defaultSort('reference', 'asc')
            ->defaultToggledColumns(['notes'])
            ->defaultGrouping('customer_name')
            ->{$method}(),
    ]);

    $component = Livewire::test(ListOrdersWithPresets::class)
        ->filterTable('status', 'pending')
        ->searchTable('ORD-01')
        ->sortTable('amount', 'desc')
        ->set('toggledTableColumns.ordered_at', false)
        ->set('tableGrouping', 'status')
        ->call('applyTablePresetView', 'p');

    $expected = [
        'filters' => ['tableFilters.status.value', fn ($kept) => $kept ? 'pending' : 'paid'],
        'search' => ['tableSearch', fn ($kept) => $kept ? 'ORD-01' : 'ORD-09'],
        'sort' => ['tableSortColumn', fn ($kept) => $kept ? 'amount' : 'reference'],
        'columns' => ['toggledTableColumns.notes', fn ($kept) => ! $kept],
        'grouping' => ['tableGrouping', fn ($kept) => $kept ? 'status' : 'customer_name'],
    ];

    foreach ($expected as $part => [$property, $value]) {
        $component->assertSet($property, $value(in_array($part, $kept, true)));
    }

    $component->assertSet('activeTablePresetKey', 'p');
})->with([
    'preserveFilters' => ['preserveFilters', ['filters']],
    'preserveSearch' => ['preserveSearch', ['search']],
    'preserveSort' => ['preserveSort', ['sort']],
    'preserveToggledColumns' => ['preserveToggledColumns', ['columns']],
    'preserveAll' => ['preserveAll', ['filters', 'search', 'sort', 'columns', 'grouping']],
]);

it('undoes a preserve call with false', function () {
    presets(fn () => ['p' => preset()->defaultSearch('ORD-09')->preserveSearch()->preserveSearch(false)]);

    Livewire::test(ListOrdersWithPresets::class)
        ->searchTable('ORD-01')
        ->call('applyTablePresetView', 'p')
        ->assertSet('tableSearch', 'ORD-09');
});

it('ignores stale or unknown parts without failing', function () {
    presets(fn () => ['stale' => preset()
        ->defaultFilters(['gone' => ['value' => 'x'], 'status' => ['value' => 'paid']])
        ->defaultSort('missing_column')
        ->defaultToggledColumns(['missing_column', 'notes'])
        ->defaultGrouping('missing_group')]);

    Livewire::test(ListOrdersWithPresets::class)
        ->call('applyTablePresetView', 'stale')
        ->assertSuccessful()
        ->assertSet('tableSortColumn', null)
        ->assertSet('tableGrouping', null)
        ->assertSet('tableFilters.status.value', 'paid')
        ->assertSet('toggledTableColumns', ['notes' => true, 'ordered_at' => false]);
});

it('flags a preset as modified like a user view', function () {
    presets(fn () => ['p' => preset('Pending')->defaultFilters(['status' => ['value' => 'pending']])]);

    $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'p');

    expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();

    $component->filterTable('status', 'paid');

    expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();

    $component->assertSee(__('tablekit::favorites-bar.modified'));
});

it('resets the page when a preset is applied', function () {
    presets(fn () => ['p' => preset()->defaultSearch('ORD')]);

    Livewire::test(ListOrdersWithPresets::class)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->call('applyTablePresetView', 'p')
        ->assertSet('paginators.page', 1);
});

describe('hidden query', function () {
    it('applies only while the preset is active', function () {
        presets(fn () => ['big' => bigOrdersPreset(), 'other' => preset('Other')->defaultSearch('ORD')]);

        $big = Order::where('amount', '>=', 500)->count();
        $all = Order::count();

        $component = Livewire::test(ListOrdersWithPresets::class)->assertCountTableRecords($all);

        $component->call('applyTablePresetView', 'big')->assertCountTableRecords($big);

        $view = storeView($this->user->id, [], ['resource' => ListOrdersWithPresets::class]);

        $component->call('applyTableView', $view->id)->assertCountTableRecords($all);
        $component->call('applyTablePresetView', 'big')->assertCountTableRecords($big);
        $component->call('applyTablePresetView', 'other')->assertCountTableRecords($all);
        $component->call('applyTablePresetView', 'big')->call('applyDefaultTableView')->assertCountTableRecords($all);
    });

    it('survives later livewire requests', function () {
        presets(fn () => ['big' => bigOrdersPreset()]);

        $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');

        $component->searchTable('ORD-0')->sortTable('amount')->call('gotoPage', 1);

        $expected = Order::where('amount', '>=', 500)->where('reference', 'like', '%ORD-0%')->count();

        $component->assertCountTableRecords($expected)->assertSet('activeTablePresetKey', 'big');
    });

    it('also filters the export query and the filtered query', function () {
        presets(fn () => ['big' => bigOrdersPreset()]);

        $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');

        expect($component->instance()->getFilteredTableQuery()->count())->toBe(Order::where('amount', '>=', 500)->count())
            ->and($component->instance()->getTableQueryForExport()->count())->toBe(Order::where('amount', '>=', 500)->count());
    });

    it('accepts a closure with an untyped or typed query parameter', function () {
        presets(fn () => [
            'typed' => preset()->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'paid')),
            'named' => preset()->modifyQueryUsing(fn ($query) => $query->where('status', 'pending')),
        ]);

        Livewire::test(ListOrdersWithPresets::class)
            ->call('applyTablePresetView', 'typed')
            ->assertCountTableRecords(Order::where('status', 'paid')->count())
            ->call('applyTablePresetView', 'named')
            ->assertCountTableRecords(Order::where('status', 'pending')->count());
    });

    it('stops applying when the preset becomes hidden or is removed', function () {
        $visible = true;

        presets(function () use (&$visible) {
            return $visible ? ['big' => bigOrdersPreset()] : [];
        });

        $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');
        $component->assertCountTableRecords(Order::where('amount', '>=', 500)->count());

        $visible = false;

        $component->call('$refresh')->assertCountTableRecords(Order::count());
    });

    it('cannot be switched on by the browser', function () {
        presets(fn () => ['big' => bigOrdersPreset()]);

        expect(fn () => Livewire::test(ListOrdersWithPresets::class)->set('activeTablePresetKey', 'big'))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('is not applied to a component without presets', function () {
        Livewire::test(ListOrdersWithPresets::class)->assertCountTableRecords(Order::count());
    });

    it('is not applied to a table that does not use the trait', function () {
        $calls = 0;

        presets(fn () => ['big' => bigOrdersPreset()->modifyQueryUsing(function (Builder $query) use (&$calls) {
            $calls++;

            return $query->where('amount', '>=', 500);
        })]);

        Livewire::test(ListOrdersWithoutViews::class)
            ->assertSuccessful()
            ->assertSet('activeTablePresetKey', null)
            ->assertCountTableRecords(Order::count());

        expect($calls)->toBe(0)->and(property_exists(ListOrdersWithoutViews::class, 'activeTablePresetKey'))->toBeFalse();
    });
});

describe('visibility', function () {
    it('hides presets that are hidden or not visible', function () {
        presets(fn () => [
            'shown' => preset('Shown preset'),
            'hidden' => preset('Hidden preset')->hidden(),
            'invisible' => preset('Invisible preset')->visible(false),
            'closure' => preset('Closure preset')->visible(fn () => false),
            'closure_hidden' => preset('Closure hidden preset')->hidden(fn () => true),
            'closure_shown' => preset('Closure shown preset')->visible(fn () => true),
        ]);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSee('Shown preset')
            ->assertSee('Closure shown preset')
            ->assertDontSee('Hidden preset')
            ->assertDontSee('Invisible preset')
            ->assertDontSee('Closure preset')
            ->assertDontSee('Closure hidden preset');
    });

    it('does not let a hidden preset be applied by key', function () {
        presets(fn () => ['secret' => bigOrdersPreset()->visible(false)->defaultSearch('ORD-01')]);

        Livewire::test(ListOrdersWithPresets::class)
            ->call('applyTablePresetView', 'secret')
            ->assertSet('activeTablePresetKey', null)
            ->assertSet('tableSearch', '')
            ->assertCountTableRecords(Order::count());
    });

    it('evaluates the visibility closure once per request', function () {
        $calls = 0;

        presets(function () use (&$calls) {
            return ['p' => preset()->visible(function () use (&$calls) {
                $calls++;

                return true;
            })];
        });

        $component = Livewire::test(ListOrdersWithPresets::class);

        expect($calls)->toBe(1);

        $calls = 0;

        $component->set('tableSearch', 'ORD-01');

        expect($calls)->toBe(1);
    });
});

describe('hostile input', function () {
    it('ignores keys that are not a visible preset', function (mixed $key) {
        presets(fn () => ['real' => preset()->defaultSearch('ORD-01')]);

        Livewire::test(ListOrdersWithPresets::class)
            ->call('applyTablePresetView', $key)
            ->assertSuccessful()
            ->assertSet('activeTablePresetKey', null)
            ->assertSet('tableSearch', '');
    })->with([
        'unknown' => 'nope',
        'empty' => '',
        'null' => null,
        'array' => [['real']],
        'nested array' => [['real' => ['x']]],
        'int' => 1,
        'float' => 1.5,
        'bool' => true,
        'huge' => str_repeat('a', 5000),
        'sql' => "real' OR '1'='1",
        'nul' => "real\0",
        'preset prefix' => 'preset:real',
        'case' => 'REAL',
    ]);
});

describe('default key', function () {
    it('replaces the default button label, icon and click behaviour', function () {
        presets(fn () => [
            'default' => PresetTableView::make()->label('Everything')->icon('star')->defaultFilters(['status' => ['value' => 'paid']]),
            'other' => preset('Other one'),
        ]);

        $component = Livewire::test(ListOrdersWithPresets::class)
            ->assertSee('Everything')
            ->assertDontSee('>Default<', escape: false)
            ->assertSeeInOrder(['Everything', 'Other one']);

        $component->call('applyDefaultTableView')
            ->assertSet('activeTablePresetKey', 'default')
            ->assertSet('tableFilters.status.value', 'paid');
    });

    it('does not list the default key preset as a separate favorite', function () {
        presets(fn () => ['default' => preset('Everything'), 'other' => preset('Other one')]);

        $html = Livewire::test(ListOrdersWithPresets::class)->html();

        expect(substr_count($html, 'Everything'))->toBe(1);
    });

    it('is applied on first load as the implicit developer default', function () {
        presets(fn () => ['default' => PresetTableView::make()->defaultFilters(['status' => ['value' => 'paid']])]);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTablePresetKey', 'default')
            ->assertSet('tableFilters.status.value', 'paid');
    });
});

describe('developer default', function () {
    it('is applied on first load even with managed defaults off', function () {
        presets(fn () => [
            'first' => preset('First'),
            'shipped' => preset('Shipped')->default()->defaultFilters(['status' => ['value' => 'shipped']]),
        ]);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTablePresetKey', 'shipped')
            ->assertSet('tableFilters.status.value', 'shipped')
            ->assertCountTableRecords(Order::where('status', 'shipped')->count());
    });

    it('takes a closure and the first truthy default wins', function () {
        presets(fn () => [
            'no' => preset()->default(fn () => false),
            'a' => preset()->default(fn () => true)->defaultSearch('ORD-01'),
            'b' => preset()->default()->defaultSearch('ORD-02'),
        ]);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSet('activeTablePresetKey', 'a')
            ->assertSet('tableSearch', 'ORD-01');
    });

    it('is not applied when the url carries table state', function () {
        presets(fn () => ['p' => preset()->default()->defaultFilters(['status' => ['value' => 'shipped']])]);

        Livewire::withQueryParams(['tableSearch' => 'ORD-05'])
            ->test(ListOrdersWithPresets::class)
            ->assertSet('activeTablePresetKey', null)
            ->assertSet('tableSearch', 'ORD-05')
            ->assertSet('tableFilters.status.value', null);
    });

    it('is applied again by the default button', function () {
        presets(fn () => ['p' => preset()->default()->defaultFilters(['status' => ['value' => 'shipped']])]);

        Livewire::test(ListOrdersWithPresets::class)
            ->filterTable('status', 'paid')
            ->call('applyDefaultTableView')
            ->assertSet('activeTablePresetKey', 'p')
            ->assertSet('tableFilters.status.value', 'shipped');
    });

    it('does not apply a hidden developer default', function () {
        presets(fn () => ['p' => preset()->default()->hidden()->defaultSearch('ORD-01')]);

        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', null)->assertSet('tableSearch', '');
    });

    it('falls back to the filament defaults without presets', function () {
        Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', null)->assertSet('activeTableViewId', null);
    });
});

describe('quick save on a preset', function () {
    it('is refused with a notification when the preset has a hidden query', function () {
        presets(fn () => ['big' => bigOrdersPreset()]);

        Livewire::test(ListOrdersWithPresets::class)
            ->call('applyTablePresetView', 'big')
            ->callAction('quickSaveTableView')
            ->assertNotified(__('tablekit::quick-save.preset_with_query_title'));

        expect(TableView::count())->toBe(0);
    });

    it('saves when the config allows it, without the hidden query', function () {
        config(['tablekit.preset_views.create_from_preset_with_query' => true]);
        presets(fn () => ['big' => bigOrdersPreset()->defaultFilters(['status' => ['value' => 'paid']])]);

        $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');
        $view = quickSave($component, 'From preset');

        expect($view->state->filters['status'])->toBe(['value' => 'paid'])
            ->and($view->resource)->toBe(ListOrdersWithPresets::class);

        $component->assertSet('activeTablePresetKey', null)->assertSet('activeTableViewId', $view->id);
    });

    it('can be allowed on the plugin', function () {
        TableKitPlugin::get()->createFromPresetWithQuery();
        presets(fn () => ['big' => bigOrdersPreset()]);

        $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');

        quickSave($component, 'Allowed');

        expect(TableView::where('name', 'Allowed')->exists())->toBeTrue();
    });

    it('saves normally on a preset without a hidden query', function () {
        presets(fn () => ['plain' => preset()->defaultSearch('ORD-01')]);

        $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'plain');
        $view = quickSave($component, 'Plain preset view');

        expect($view->state->search)->toBe('ORD-01');
    });
});

describe('bar rendering', function () {
    it('shows the badge, tooltip, color and icon of a preset', function () {
        presets(fn () => ['p' => preset('With extras')
            ->icon('truck')
            ->color('success')
            ->badge(fn () => Order::where('status', 'shipped')->count())
            ->badgeColor('warning')
            ->tooltip('Hover text here')]);

        $count = Order::where('status', 'shipped')->count();

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSee('With extras')
            ->assertSee((string) $count)
            ->assertSeeHtml('Hover text here')
            ->assertSeeHtml('fi-color-success');
    });

    it('evaluates a badge once per render', function () {
        $calls = 0;

        presets(function () use (&$calls) {
            return ['p' => preset()->badge(function () use (&$calls) {
                $calls++;

                return 7;
            })];
        });

        $component = Livewire::test(ListOrdersWithPresets::class);
        $before = $calls;

        $component->call('$refresh');

        expect($calls - $before)->toBe(1);
    });

    it('ignores an unknown icon key and an unknown color', function () {
        presets(fn () => ['p' => preset('Odd one')->icon('does-not-exist')->color('not-a-color')]);

        Livewire::test(ListOrdersWithPresets::class)->assertSuccessful()->assertSee('Odd one');
    });

    it('lists favorite presets in order before user views with a divider', function () {
        presets(fn () => ['a' => preset('Alpha preset'), 'b' => preset('Beta preset'), 'c' => preset('Gamma preset')->favorite(false)]);
        storeView($this->user->id, [], ['name' => 'My own view', 'resource' => ListOrdersWithPresets::class]);

        Livewire::test(ListOrdersWithPresets::class)
            ->assertSeeInOrder(['Default', 'Alpha preset', 'Beta preset', 'My own view', 'Save view'])
            ->assertDontSee('Gamma preset')
            ->assertSeeHtml('fi-tablekit-divider');
    });

    it('can drop the divider on config and plugin', function () {
        presets(fn () => ['a' => preset('Alpha preset')]);
        storeView($this->user->id, [], ['name' => 'My own view', 'resource' => ListOrdersWithPresets::class]);

        config(['tablekit.favorites_bar.divider' => false]);

        Livewire::test(ListOrdersWithPresets::class)->assertDontSeeHtml('fi-tablekit-divider');

        config(['tablekit.favorites_bar.divider' => true]);
        TableKitPlugin::get()->favoritesBarDivider(false);

        Livewire::test(ListOrdersWithPresets::class)->assertDontSeeHtml('fi-tablekit-divider');
    });

    it('rejects a preset list with a wrong value or key', function (array $presets) {
        presets($presets);

        try {
            Livewire::test(ListOrdersWithPresets::class);
        } catch (Throwable $exception) {
            while ($exception->getPrevious()) {
                $exception = $exception->getPrevious();
            }
        }

        expect($exception ?? null)->toBeInstanceOf(InvalidArgumentException::class);
    })->with([
        'not a preset' => [['a' => 'nope']],
        'bad key' => [['bad key' => PresetTableView::make()]],
        'long key' => [[str_repeat('k', 101) => PresetTableView::make()]],
    ]);
});
