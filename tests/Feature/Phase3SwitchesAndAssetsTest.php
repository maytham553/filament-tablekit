<?php

use Elite\TableViews\Support\TableViewsOptions;
use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Off;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

describe('options', function () {
    it('has the documented defaults in config', function () {
        $options = TableViewsOptions::resolve();

        expect($options->multiSortEnabled())->toBeTrue()
            ->and($options->maxSorts())->toBe(5)
            ->and($options->quickFiltersEnabled())->toBeFalse()
            ->and($options->limitIndicatorLabels())->toBe(2)
            ->and($options->columnReorderingEnabled())->toBeTrue();
    });

    it('resolves from config when the panel has no plugin', function () {
        Filament::setCurrentPanel(Filament::getPanel('team'));
        config(['table-views.quick_filters.enabled' => true, 'table-views.multi_sort.max' => 3]);

        expect(TableViewsOptions::resolve()->quickFiltersEnabled())->toBeTrue()
            ->and(TableViewsOptions::resolve()->maxSorts())->toBe(3);
    });

    it('lets the plugin override every Phase 3 option, closures included', function () {
        TableViewsPlugin::get()
            ->multiSort(fn (): bool => false, max: 4)
            ->quickFilters(fn (): bool => true)
            ->limitIndicatorLabels(3)
            ->columnReordering(false);

        $options = TableViewsOptions::resolve();

        expect($options->multiSortEnabled())->toBeFalse()
            ->and($options->maxSorts())->toBe(4)
            ->and($options->quickFiltersEnabled())->toBeTrue()
            ->and($options->limitIndicatorLabels())->toBe(3)
            ->and($options->columnReorderingEnabled())->toBeFalse();
    });

    it('keeps the config maximum when the plugin gives none and clamps odd values', function () {
        config(['table-views.multi_sort.max' => 7]);
        TableViewsPlugin::get()->multiSort();

        expect(TableViewsOptions::resolve()->maxSorts())->toBe(7);

        TableViewsPlugin::get()->multiSort(true, 500);

        expect(TableViewsOptions::resolve()->maxSorts())->toBe(20);

        TableViewsPlugin::get()->multiSort(true, 1);

        expect(TableViewsOptions::resolve()->maxSorts())->toBe(2);
    });

    it('turns multi-sort off by the plugin', function () {
        TableViewsPlugin::get()->multiSort(false);

        $component = Livewire::test(ListOrdersPhase3::class)->assertDontSeeHtml('data-table-views-sort-trigger')->call('addTableViewSort', 'amount');

        expect($component->get('tableSort'))->toBeNull();
    });

    it('lets each table opt out on its own, whatever the global switches say', function () {
        TableViewsPlugin::get()->multiSort()->quickFilters()->columnReordering();

        $off = Livewire::test(ListOrdersPhase3Off::class)->html();

        expect($off)->not->toContain('data-table-views-sort-trigger')
            ->and($off)->not->toContain('fi-table-views-quick-filters');

        $on = Livewire::test(ListOrdersPhase3::class)->html();

        expect($on)->toContain('data-table-views-sort-trigger')->and($on)->toContain('fi-table-views-quick-filters');
    });

    it('does not expose the switches to the browser', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        foreach (['multiSortTableViews', 'quickFiltersTableViews', 'columnOrderTableViews'] as $method) {
            expect(fn () => $component->call($method))->toThrow(MethodNotFoundException::class);
        }
    });

    it('does not expose internals as public methods', function () {
        foreach (['effectiveTableViewSorts', 'commitTableViewSorts', 'setTableViewSorts', 'getTableViewQuickFilterChips', 'setTableViewFavoriteFilters', 'applyTableViewColumns', 'captureTableViewColumnOrder', 'orderTableViewColumnItems'] as $method) {
            expect((new ReflectionMethod(ListOrdersPhase3::class, $method))->isPublic())->toBeFalse($method);
        }
    });
});

describe('icons', function () {
    $aliases = [
        'table-views::sort.open', 'table-views::sort.asc', 'table-views::sort.desc', 'table-views::sort.remove',
        'table-views::quick-filters.chevron', 'table-views::quick-filters.favorite', 'table-views::quick-filters.unfavorite',
        'table-views::reorder.handle',
    ];

    it('registers a heroicon default for every new alias', function (string $alias) {
        expect(FilamentIcon::resolve($alias))->toBeInstanceOf(Heroicon::class);
    })->with($aliases);

    it('never hard-codes an icon in the new views', function () {
        foreach (['sort-button', 'quick-filters', 'components/sortable', 'components/sortable-handle'] as $view) {
            $source = file_get_contents(__DIR__."/../../resources/views/{$view}.blade.php");

            expect($source)->not->toContain('heroicon')->and($source)->not->toContain('x-heroicon');
        }
    });

    it('renders a remapped alias', function () {
        FilamentIcon::register([
            'table-views::sort.open' => new HtmlString('<svg data-test="remapped-sort"></svg>'),
            'table-views::quick-filters.chevron' => new HtmlString('<svg data-test="remapped-chevron"></svg>'),
            'table-views::reorder.handle' => new HtmlString('<svg data-test="remapped-up"></svg>'),
        ]);

        config(['table-views.quick_filters.enabled' => true]);

        Livewire::test(ListOrdersPhase3::class)
            ->call('addTableViewSort', 'amount')
            ->call('addTableViewSort', 'reference')
            ->assertSeeHtml('data-test="remapped-sort"')
            ->assertSeeHtml('data-test="remapped-chevron"')
            ->assertSeeHtml('data-test="remapped-up"');
    });
});

describe('translations', function () {
    it('ships every key the code and the views use, in both languages', function () {
        $keys = [];

        foreach ([...glob(__DIR__.'/../../src/**/*.php'), ...glob(__DIR__.'/../../src/*.php'), ...glob(__DIR__.'/../../resources/views/*.blade.php'), ...glob(__DIR__.'/../../resources/views/components/*.blade.php')] as $file) {
            foreach (file($file) as $line) {
                if (! str_contains($line, '__(')) {
                    continue;
                }

                preg_match_all('/table-views::([a-z-]+)\.([a-z_.]+)[\'"]/', $line, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    if (! str_ends_with($match[2], '.')) {
                        $keys[] = [$match[1], $match[2]];
                    }
                }
            }
        }

        expect($keys)->not->toBeEmpty();

        foreach (['en', 'ar'] as $locale) {
            foreach ($keys as [$file, $key]) {
                $lines = require __DIR__."/../../resources/lang/{$locale}/{$file}.php";

                expect(data_get($lines, $key))->not->toBeNull("{$locale} {$file}.{$key}");
            }
        }
    });

    it('renders the Phase 3 labels in arabic', function () {
        app()->setLocale('ar');
        config(['table-views.quick_filters.enabled' => true]);

        $html = Livewire::test(ListOrdersPhase3::class)->html();

        expect($html)->toContain(__('table-views::sort.label'))
            ->and(__('table-views::quick-filters.more', ['count' => 3]))->toContain('3');
    });
});
