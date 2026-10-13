<?php

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use TableKit\Support\TableKitOptions;
use TableKit\TableKitPlugin;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Off;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

describe('options', function () {
    it('has the documented defaults in config', function () {
        $options = TableKitOptions::resolve();

        expect($options->multiSortEnabled())->toBeTrue()
            ->and($options->maxSorts())->toBe(5)
            ->and($options->quickFiltersEnabled())->toBeFalse()
            ->and($options->limitIndicatorLabels())->toBe(2)
            ->and($options->columnReorderingEnabled())->toBeTrue();
    });

    it('resolves from config when the panel has no plugin', function () {
        Filament::setCurrentPanel(Filament::getPanel('team'));
        config(['tablekit.quick_filters.enabled' => true, 'tablekit.multi_sort.max' => 3]);

        expect(TableKitOptions::resolve()->quickFiltersEnabled())->toBeTrue()
            ->and(TableKitOptions::resolve()->maxSorts())->toBe(3);
    });

    it('lets the plugin override every Phase 3 option, closures included', function () {
        TableKitPlugin::get()
            ->multiSort(fn (): bool => false, max: 4)
            ->quickFilters(fn (): bool => true)
            ->limitIndicatorLabels(3)
            ->columnReordering(false);

        $options = TableKitOptions::resolve();

        expect($options->multiSortEnabled())->toBeFalse()
            ->and($options->maxSorts())->toBe(4)
            ->and($options->quickFiltersEnabled())->toBeTrue()
            ->and($options->limitIndicatorLabels())->toBe(3)
            ->and($options->columnReorderingEnabled())->toBeFalse();
    });

    it('keeps the config maximum when the plugin gives none and clamps odd values', function () {
        config(['tablekit.multi_sort.max' => 7]);
        TableKitPlugin::get()->multiSort();

        expect(TableKitOptions::resolve()->maxSorts())->toBe(7);

        TableKitPlugin::get()->multiSort(true, 500);

        expect(TableKitOptions::resolve()->maxSorts())->toBe(20);

        TableKitPlugin::get()->multiSort(true, 1);

        expect(TableKitOptions::resolve()->maxSorts())->toBe(2);
    });

    it('turns multi-sort off by the plugin', function () {
        TableKitPlugin::get()->multiSort(false);

        $component = Livewire::test(ListOrdersPhase3::class)->assertDontSeeHtml('data-tablekit-sort-trigger')->call('addTableViewSort', 'amount');

        expect($component->get('tableSort'))->toBeNull();
    });

    it('lets each table opt out on its own, whatever the global switches say', function () {
        TableKitPlugin::get()->multiSort()->quickFilters()->columnReordering();

        $off = Livewire::test(ListOrdersPhase3Off::class)->html();

        expect($off)->not->toContain('data-tablekit-sort-trigger')
            ->and($off)->not->toContain('fi-tablekit-quick-filters');

        $on = Livewire::test(ListOrdersPhase3::class)->html();

        expect($on)->toContain('data-tablekit-sort-trigger')->and($on)->toContain('fi-tablekit-quick-filters');
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
        'tablekit::sort.open', 'tablekit::sort.asc', 'tablekit::sort.desc', 'tablekit::sort.remove',
        'tablekit::quick-filters.chevron', 'tablekit::quick-filters.favorite', 'tablekit::quick-filters.unfavorite',
        'tablekit::reorder.handle',
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
            'tablekit::sort.open' => new HtmlString('<svg data-test="remapped-sort"></svg>'),
            'tablekit::quick-filters.chevron' => new HtmlString('<svg data-test="remapped-chevron"></svg>'),
            'tablekit::reorder.handle' => new HtmlString('<svg data-test="remapped-up"></svg>'),
        ]);

        config(['tablekit.quick_filters.enabled' => true]);

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

                preg_match_all('/tablekit::([a-z-]+)\.([a-z_.]+)[\'"]/', $line, $matches, PREG_SET_ORDER);

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
        config(['tablekit.quick_filters.enabled' => true]);

        $html = Livewire::test(ListOrdersPhase3::class)->html();

        expect($html)->toContain(__('tablekit::sort.label'))
            ->and(__('tablekit::quick-filters.more', ['count' => 3]))->toContain('3');
    });
});
