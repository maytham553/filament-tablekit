<?php

use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use TableKit\Support\FavoriteFilters;
use TableKit\TableKitPlugin;
use TableKit\TableKitServiceProvider;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersNoToggles;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3DefaultedFilter;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Deferred;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Indicators;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Off;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Preload;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3ShowsIndicators;
use TableKit\Tests\Fixtures\Team;
use TableKit\Tests\Fixtures\Widgets\OrdersWidget;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
    config(['tablekit.quick_filters.enabled' => true]);
});

describe('the bar', function () {
    it('is off by default and leaves Filament\'s own indicators alone', function () {
        config(['tablekit.quick_filters.enabled' => false]);

        $html = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid')->html();

        expect($html)->toContain('fi-ta-filter-indicators')
            ->and($html)->not->toContain('fi-tablekit-quick-filters')
            ->and($html)->not->toContain('data-quick-filter');
    });

    it('replaces Filament\'s indicator bar when on', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid')->html();

        expect($html)->not->toContain('fi-ta-filter-indicators')
            ->and($html)->toContain('fi-tablekit-quick-filters')
            ->and(chipNames($html))->toBe(['status']);
    });

    it('still counts active filters on the filter button', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid')->set('tableFilters.big.isActive', true);

        expect($component->instance()->getTable()->getActiveFiltersCount())->toBe(2);
    });

    it('shows a favorite filter as a chip while it is inactive', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->html();

        expect(chipNames($html))->toBe(['status'])
            ->and(chipHtml($html, 'status'))->toContain('data-active="false"')
            ->and(chipHtml($html, 'status'))->not->toContain('removeTableFilter');
    });

    it('shows the indicator label of an active filter and a remove button', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid')->html();

        expect(chipHtml($html, 'status'))->toContain('Status: Paid')
            ->and(chipHtml($html, 'status'))->toContain('data-active="true"')
            ->and(chipHtml($html, 'status'))->toContain("removeTableFilter('status')");
    });

    it('shows chips in the order of the table\'s filters, active or favorite', function () {
        $html = Livewire::test(ListOrdersPhase3::class)
            ->set('tableFilters.big.isActive', true)
            ->set('tableFilters.multi.values', ['paid', 'shipped'])
            ->html();

        expect(chipNames($html))->toBe(['status', 'multi', 'big']);
    });

    it('draws no bar when nothing is active or favorite', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'status');

        expect($component->get('tableViewFavoriteFilters'))->toBe([])
            ->and($component->html())->not->toContain('fi-tablekit-quick-filters');
    });

    it('draws one chip for a filter with several fields and edits all of them in its popover', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->set('tableFilters.ordered.from', '2026-02-01')
            ->set('tableFilters.ordered.until', '2026-03-01')
            ->call('openTableViewQuickFilter', 'ordered');

        $html = $component->html();

        expect(chipNames($html))->toBe(['status', 'ordered'])
            ->and(chipHtml($html, 'ordered'))->toContain('From 2026-02-01 · Until 2026-03-01')
            ->and(array_keys(popoverBindings($html, 'ordered')))->toBe(['tableViewQuickFilterState.ordered.from', 'tableViewQuickFilterState.ordered.until']);
    });

    it('draws chips for the search and the column searches like Filament', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->searchTable('ORD-01')->html();

        expect($html)->toContain('Search: ORD-01')
            ->and($html)->toContain('wire:click="resetTableSearch"');
    });

    it('draws the clear-all button only when something can be removed', function () {
        expect(Livewire::test(ListOrdersPhase3::class)->html())->not->toContain('wire:click="removeTableFilters"')
            ->and(Livewire::test(ListOrdersPhase3::class)->set('tableFilters.big.isActive', true)->html())->toContain('wire:click="removeTableFilters"');
    });

    it('also works on a relation manager and a table widget', function () {
        $widget = Livewire::test(OrdersWidget::class)->set('tableFilters.status.value', 'paid')->html();

        expect($widget)->toContain('fi-tablekit-quick-filters')->toContain('data-quick-filter="status"');
    });

    it('leaves a table without filters alone', function () {
        $html = Livewire::test(ListOrdersNoToggles::class)->html();

        expect($html)->not->toContain('fi-tablekit-quick-filters');
    });
});

describe('the popover', function () {
    it('renders no filter form until its chip is opened', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        expect(popoverBindings($component->html(), 'status'))->toBe([])
            ->and(chipHtml($component->html(), 'status'))->toContain('openTableViewQuickFilter(');

        $component->call('openTableViewQuickFilter', 'status');

        expect(popoverBindings($component->html(), 'status'))->not->toBe([]);
    });

    it('binds its fields to a state path of its own, live on a normal table', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', 'status')->html();

        expect(popoverBindings($html, 'status'))->toBe(['tableViewQuickFilterState.status.value' => '.live']);
    });

    it('binds its fields without live on a deferred table', function () {
        $html = Livewire::test(ListOrdersPhase3Deferred::class)->call('openTableViewQuickFilter', 'status')->html();

        expect(popoverBindings($html, 'status'))->toBe(['tableViewQuickFilterState.status.value' => '']);
    });

    it('applies a change made through its bound field at once and updates the chip', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', 'status');
        [$path] = array_keys(popoverBindings($component->html(), 'status'));

        $component->set($path, 'shipped');

        $component->assertCountTableRecords(Order::where('status', 'shipped')->count());

        expect($component->get('tableFilters.status.value'))->toBe('shipped')
            ->and(chipHtml($component->html(), 'status'))->toContain('Status: Shipped');
    });

    it('edits every field of a multi-field filter through its bound fields', function () {
        $from = now()->startOfYear()->addDays(10)->toDateString();
        $until = now()->startOfYear()->addDays(20)->toDateString();

        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.ordered.from', $from)->call('openTableViewQuickFilter', 'ordered');
        $paths = array_keys(popoverBindings($component->html(), 'ordered'));

        $component->set($paths[1], $until);

        expect($component->get('tableFilters.ordered.from'))->toBe($from)
            ->and($component->get('tableFilters.ordered.until'))->toBe($until);

        $component->assertCountTableRecords(Order::whereDate('ordered_at', '>=', $from)->whereDate('ordered_at', '<=', $until)->count());
    });

    it('shows a change made in the main filter form', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', 'status')->set('tableFilters.status.value', 'paid');

        expect($component->get('tableViewQuickFilterState.status.value'))->toBe('paid');

        $component->call('removeTableFilter', 'status');

        expect($component->get('tableViewQuickFilterState.status.value'))->toBeNull();
    });

    it('copies only the open filter into the filter state', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', 'status')
            ->set('tableViewQuickFilterState.big.isActive', true)
            ->set('tableViewQuickFilterState.status.value', 'paid');

        expect($component->get('tableFilters.big.isActive'))->toBeFalsy()
            ->and($component->get('tableFilters.status.value'))->toBe('paid')
            ->and(array_keys($component->get('tableViewQuickFilterState')))->toBe(['status']);
    });

    it('ignores a change when no chip is open', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->set('tableViewQuickFilterState.status.value', 'paid');

        expect($component->get('tableFilters.status.value'))->toBeNull();
    });

    it('ignores a change when quick filters are off', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', 'status');

        config(['tablekit.quick_filters.enabled' => false]);

        $component->set('tableViewQuickFilterState.status.value', 'paid');

        expect($component->get('tableFilters.status.value'))->toBeNull();
    });

    it('opens only a visible filter and only by name', function (mixed $name) {
        $component = Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', $name);

        expect($component->get('tableViewOpenQuickFilter'))->toBeNull();
    })->with(['unknown' => 'nope', 'hidden' => 'secret', 'empty' => '', 'array' => [['status']], 'int' => 3, 'null' => null]);

    it('cannot be opened from the browser', function () {
        expect(fn () => Livewire::test(ListOrdersPhase3::class)->set('tableViewOpenQuickFilter', 'status'))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('does nothing when quick filters are off', function () {
        config(['tablekit.quick_filters.enabled' => false]);

        expect(Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', 'status')->get('tableViewOpenQuickFilter'))->toBeNull();
    });

    it('closes only the chip that is open', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', 'status')->call('closeTableViewQuickFilter', 'big');

        expect($component->get('tableViewOpenQuickFilter'))->toBe('status');

        $component->call('closeTableViewQuickFilter', 'status');

        expect($component->get('tableViewOpenQuickFilter'))->toBeNull()
            ->and($component->get('tableViewQuickFilterState'))->toBe([])
            ->and(popoverBindings($component->html(), 'status'))->toBe([]);
    });

    it('renders the form of the open chip only', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.big.isActive', true)->call('openTableViewQuickFilter', 'big');

        expect(chipHtml($component->html(), 'big'))->toContain('tableViewQuickFilterState.big.isActive')
            ->and(popoverBindings($component->html(), 'status'))->toBe([]);

        $component->call('openTableViewQuickFilter', 'status');

        expect(popoverBindings($component->html(), 'status'))->toHaveCount(1)
            ->and(chipHtml($component->html(), 'big'))->not->toContain('tableViewQuickFilterState');
    });

    it('has no apply button without deferred filters', function () {
        expect(chipHtml(Livewire::test(ListOrdersPhase3::class)->html(), 'status'))->not->toContain('wire:click="applyTableFilters"');
    });

    it('applies a deferred filter only through the apply button', function () {
        $component = Livewire::test(ListOrdersPhase3Deferred::class)->call('openTableViewQuickFilter', 'status');

        expect(chipHtml($component->html(), 'status'))->toContain('wire:click="applyTableFilters"');

        [$path] = array_keys(popoverBindings($component->html(), 'status'));

        $component->set($path, 'paid');

        expect($component->get('tableDeferredFilters.status.value'))->toBe('paid')
            ->and($component->get('tableFilters.status.value'))->toBeNull()
            ->and(chipHtml($component->html(), 'status'))->toContain('data-active="false"');

        $component->call('applyTableFilters')->assertCountTableRecords(Order::where('status', 'paid')->count());

        expect($component->get('tableFilters.status.value'))->toBe('paid')
            ->and(chipHtml($component->html(), 'status'))->toContain('Status: Paid');
    });

    it('keeps an unapplied deferred edit through other requests', function () {
        $component = Livewire::test(ListOrdersPhase3Deferred::class)->call('openTableViewQuickFilter', 'status')->set('tableViewQuickFilterState.status.value', 'paid');

        $component->call('$refresh');

        expect($component->get('tableViewQuickFilterState.status.value'))->toBe('paid')
            ->and($component->get('tableFilters.status.value'))->toBeNull();
    });

    it('pins and unpins from the popover header', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->html();

        expect(chipHtml($html, 'status'))->toContain("togglePinnedTableViewFilter('status')");
    });

    it('does not repeat an id or a wire key of the main filter form', function (string $page) {
        $component = Livewire::test($page)
            ->set('tableFilters.status.value', 'paid')
            ->set('tableFilters.ordered.from', '2026-02-01')
            ->call('openTableViewQuickFilter', 'status');

        expect(popoverBindings($component->html(), 'status'))->not->toBe([]);

        $component->call('openTableViewQuickFilter', 'ordered');

        $html = $component->html();

        expect(popoverBindings($html, 'ordered'))->not->toBe([])
            ->and(duplicateAttributeValues($html, 'id'))->toBe([])
            // Filament's pagination renders its own next link key twice, with or without this plugin.
            ->and(array_values(array_filter(duplicateAttributeValues($html, 'wire:key'), fn (string $key): bool => ! str_contains($key, 'pagination'))))->toBe([]);
    })->with([
        'normal' => ListOrdersPhase3::class,
        'deferred' => ListOrdersPhase3Deferred::class,
    ]);

    it('gives every popover field an id that differs from the main filter form', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->call('openTableViewQuickFilter', 'status')->html();

        expect($html)->toContain('id="tableFilters.status.value"')
            ->and($html)->toContain('id="tableViewQuickFilterState.status.value"');
    });
});

describe('removing', function () {
    it('clears the filter with the chip button and keeps a favorite chip', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid')->call('removeTableFilter', 'status');

        expect($component->get('tableFilters.status.value'))->toBeNull()
            ->and(chipNames($component->html()))->toBe(['status'])
            ->and(chipHtml($component->html(), 'status'))->toContain('data-active="false"');

        $component->assertCountTableRecords(Order::count());
    });

    it('drops the chip of a filter that is not a favorite once it is cleared', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.big.isActive', true);

        expect(chipNames($component->html()))->toContain('big');

        $component->call('removeTableFilter', 'big');

        expect(chipNames($component->html()))->not->toContain('big');
    });

    it('clears one field of a multi-field filter through Filament\'s own handler', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->set('tableFilters.ordered.from', '2026-02-01')
            ->set('tableFilters.ordered.until', '2026-03-01')
            ->call('removeTableFilter', 'ordered');

        expect($component->get('tableFilters.ordered.from'))->toBeNull()
            ->and($component->get('tableFilters.ordered.until'))->toBeNull()
            ->and(chipNames($component->html()))->not->toContain('ordered');
    });

    it('clears everything with the clear-all button, search included', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->searchTable('ORD')->set('tableFilters.big.isActive', true)->call('removeTableFilters');

        expect($component->get('tableSearch'))->toBe('')
            ->and($component->get('tableFilters.big.isActive'))->toBeFalsy();

        $component->assertCountTableRecords(Order::count());
    });

    it('applies a removal on a deferred table', function () {
        $component = Livewire::test(ListOrdersPhase3Deferred::class)->set('tableDeferredFilters.status.value', 'paid')->call('applyTableFilters')->call('removeTableFilter', 'status');

        expect($component->get('tableFilters.status.value'))->toBeNull()->and($component->get('tableDeferredFilters.status.value'))->toBeNull();

        $component->assertCountTableRecords(Order::count());
    });
});

describe('indicators', function () {
    it('shows no remove button for a filter whose indicators are all locked', function () {
        $html = Livewire::test(ListOrdersPhase3Indicators::class)->set('tableFilters.mixed.b', 'w')->html();

        expect(chipNames($html))->toContain('mixed')
            ->and(chipHtml($html, 'mixed'))->toContain('B=w')
            ->and(chipHtml($html, 'mixed'))->not->toContain("removeTableFilter('mixed')")
            ->and(chipHtml($html, 'mixed'))->not->toContain("removeTableViewQuickFilter('mixed')");
    });

    it('removes only the removable indicators of a chip with a locked one', function () {
        $component = Livewire::test(ListOrdersPhase3Indicators::class)->set('tableFilters.mixed.a', 'hello')->set('tableFilters.mixed.b', 'w');

        $chip = chipHtml($component->html(), 'mixed');

        expect($chip)->toContain('A=hello · B=w')
            ->and($chip)->toContain("removeTableViewQuickFilter('mixed')")
            ->and($chip)->not->toContain("removeTableFilter('mixed')");

        $component->call('removeTableViewQuickFilter', 'mixed');

        expect($component->get('tableFilters.mixed.a'))->toBeNull()
            ->and($component->get('tableFilters.mixed.b'))->toBe('w')
            ->and(chipHtml($component->html(), 'mixed'))->toContain('B=w')->not->toContain('A=hello');
    });

    it('clears the whole filter when every indicator is removable', function () {
        $html = Livewire::test(ListOrdersPhase3Indicators::class)->set('tableFilters.mixed.a', 'hello')->html();

        expect(chipHtml($html, 'mixed'))->toContain("removeTableFilter('mixed')");
    });

    it('does not clear a locked indicator when the quick remove is called for it', function () {
        $component = Livewire::test(ListOrdersPhase3Indicators::class)->set('tableFilters.mixed.b', 'w')->call('removeTableViewQuickFilter', 'mixed');

        expect($component->get('tableFilters.mixed.b'))->toBe('w');
    });

    it('ignores a quick remove of an unknown filter or when quick filters are off', function () {
        $component = Livewire::test(ListOrdersPhase3Indicators::class)->set('tableFilters.mixed.a', 'hello')->call('removeTableViewQuickFilter', 'nope')->call('removeTableViewQuickFilter', ['mixed']);

        expect($component->get('tableFilters.mixed.a'))->toBe('hello');

        config(['tablekit.quick_filters.enabled' => false]);

        expect($component->call('removeTableViewQuickFilter', 'mixed')->get('tableFilters.mixed.a'))->toBe('hello');
    });

    it('draws a filter without indicators as active while it holds a value and lets it be removed', function () {
        $component = Livewire::test(ListOrdersPhase3Indicators::class)->set('tableFilters.noind.q', 'ORD-01');

        expect(chipNames($component->html()))->toContain('noind')
            ->and(chipHtml($component->html(), 'noind'))->toContain('data-active="true"')
            ->and(chipHtml($component->html(), 'noind'))->toContain('Reference contains')
            ->and(chipHtml($component->html(), 'noind'))->toContain("removeTableFilter('noind')");

        $component->call('removeTableFilter', 'noind');

        expect(chipNames($component->html()))->not->toContain('noind');
    });

    it('draws a pinned filter without indicators as inactive until it holds a value', function () {
        $component = Livewire::test(ListOrdersPhase3Indicators::class)->call('togglePinnedTableViewFilter', 'noind');

        expect(chipHtml($component->html(), 'noind'))->toContain('data-active="false"')->not->toContain('removeTableFilter');

        $component->set('tableFilters.noind.q', 'ORD-01');

        expect(chipHtml($component->html(), 'noind'))->toContain('data-active="true"');
    });

    it('does not draw a filter as active only because its field has a default', function () {
        $component = Livewire::test(ListOrdersPhase3DefaultedFilter::class);

        expect($component->get('tableFilters.period.p'))->toBe('all')
            ->and(chipNames($component->html()))->not->toContain('period');

        $component->set('tableFilters.period.p', 'week');

        expect(chipHtml($component->html(), 'period'))->toContain('data-active="true"');

        $component->set('tableFilters.period.p', 'all');

        expect(chipNames($component->html()))->not->toContain('period');
    });

    it('names the indicator in the chip\'s accessible label', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid');

        expect(chipHtml($component->html(), 'status'))->toMatch('/aria-label="Edit Status: [^"]*Paid[^"]*"/');

        app()->setLocale('ar');

        $arabic = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid');

        expect(chipHtml($arabic->html(), 'status'))->toMatch('/aria-label="تعديل Status: [^"]*Paid[^"]*"/');
    });

    it('keeps the loading line and the form under different wire keys', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        $closed = chipHtml($component->html(), 'status');

        expect($closed)->toMatch('/wire:key="tablekit-quick-filter-loading-[^"]+-status"/')
            ->and($closed)->not->toContain('tablekit-quick-filter-form-');

        $opened = chipHtml($component->call('openTableViewQuickFilter', 'status')->html(), 'status');

        expect($opened)->toMatch('/wire:key="tablekit-quick-filter-form-[^"]+-status"/')
            ->and($opened)->not->toContain('tablekit-quick-filter-loading-');
    });

    it('does not treat an off toggle as active', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.big.isActive', false);

        expect(chipNames($component->html()))->not->toContain('big');
    });
});

describe('Filament\'s own bar', function () {
    it('stays hidden after resetTable() rebuilds the table', function () {
        $table = Livewire::test(ListOrdersPhase3::class)->call('resetTable')->instance()->getTable();

        expect((fn () => $this->areFilterIndicatorsHidden)->call($table))->toBeTrue();
    });

    it('stays hidden when the table calls hiddenFilterIndicators(false)', function () {
        ListOrdersPhase3ShowsIndicators::$hide = false;

        $html = Livewire::test(ListOrdersPhase3ShowsIndicators::class)->set('tableFilters.status.value', 'paid')->html();

        expect($html)->not->toContain('fi-ta-filter-indicators')->and($html)->toContain('fi-tablekit-quick-filters');
    });

    it('stays hidden when the table calls hiddenFilterIndicators(true)', function () {
        ListOrdersPhase3ShowsIndicators::$hide = true;

        $html = Livewire::test(ListOrdersPhase3ShowsIndicators::class)->set('tableFilters.status.value', 'paid')->html();

        ListOrdersPhase3ShowsIndicators::$hide = false;

        expect($html)->not->toContain('fi-ta-filter-indicators')->and($html)->toContain('fi-tablekit-quick-filters');
    });

    it('is the table\'s own again when quick filters are off', function () {
        config(['tablekit.quick_filters.enabled' => false]);
        ListOrdersPhase3ShowsIndicators::$hide = false;

        $html = Livewire::test(ListOrdersPhase3ShowsIndicators::class)->set('tableFilters.status.value', 'paid')->html();

        expect($html)->toContain('fi-ta-filter-indicators')->and($html)->not->toContain('fi-tablekit-quick-filters');
    });
});

describe('cost', function () {
    function searchQueryCount(string $page, ?string $openChip = null): int
    {
        $component = Livewire::test($page);

        if ($openChip !== null) {
            $component->call('openTableViewQuickFilter', $openChip);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $component->set('tableSearch', 'ORD-01');

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    beforeEach(function () {
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            Team::create(['name' => $name]);
        }
    });

    it('adds no query to a search request with the chips closed', function () {
        config(['tablekit.quick_filters.enabled' => false]);
        $off = searchQueryCount(ListOrdersPhase3Preload::class);

        config(['tablekit.quick_filters.enabled' => true]);
        $on = searchQueryCount(ListOrdersPhase3Preload::class);

        expect($on)->toBe($off);
    });

    it('adds no query to a filter request with the chips closed', function () {
        config(['tablekit.quick_filters.enabled' => false]);
        $component = Livewire::test(ListOrdersPhase3Preload::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->set('tableFilters.status.value', 'paid');
        $off = count(DB::getQueryLog());
        DB::disableQueryLog();

        config(['tablekit.quick_filters.enabled' => true]);
        $component = Livewire::test(ListOrdersPhase3Preload::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->set('tableFilters.status.value', 'paid');
        $on = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($on)->toBe($off);
    });

    it('pays for the options of a preloaded filter only while its chip is open', function () {
        $closed = searchQueryCount(ListOrdersPhase3Preload::class);
        $open = searchQueryCount(ListOrdersPhase3Preload::class, 'team_id');

        expect($open)->toBeGreaterThan($closed);
    });

    it('returns to the closed cost once the chip is closed again', function () {
        $closed = searchQueryCount(ListOrdersPhase3Preload::class);

        $component = Livewire::test(ListOrdersPhase3Preload::class)->call('openTableViewQuickFilter', 'team_id')->call('closeTableViewQuickFilter', 'team_id');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->set('tableSearch', 'ORD-01');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($count)->toBe($closed);
    });
});

describe('long labels', function () {
    it('shortens a multi-select chip after the configured number of values', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.multi.values', ['pending', 'paid', 'shipped', 'cancelled'])->html();

        expect(chipHtml($html, 'multi'))->toContain('Statuses: Pending, Paid &amp; 2 more')
            ->and(chipHtml($html, 'multi'))->not->toContain('Shipped, ');
    });

    it('shows all values up to the limit', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.multi.values', ['pending', 'paid'])->html();

        expect(chipHtml($html, 'multi'))->toContain('Statuses: Pending &amp; Paid');
    });

    it('can be switched off in config and by the plugin', function () {
        config(['tablekit.quick_filters.limit_labels' => 0]);

        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.multi.values', ['pending', 'paid', 'shipped']);

        expect(chipHtml($component->html(), 'multi'))->toContain('Pending, Paid &amp; Shipped');

        config(['tablekit.quick_filters.limit_labels' => 1]);
        TableKitPlugin::get()->limitIndicatorLabels(null);

        expect(chipHtml($component->call('$refresh')->html(), 'multi'))->toContain('Pending, Paid &amp; Shipped');

        TableKitPlugin::get()->limitIndicatorLabels(1);

        expect(chipHtml($component->call('$refresh')->html(), 'multi'))->toContain('Pending &amp; 2 more');
    });

    it('never shortens a label it cannot split back exactly', function () {
        $component = Livewire::test(ListOrdersPhase3::class);
        $filter = $component->instance()->getTable()->getFilter('multi');
        $component->set('tableFilters.multi.values', ['pending', 'paid', 'shipped']);

        $shortened = callProtected($component, 'shortenTableViewIndicatorLabel', 'multi', $filter, 'Statuses: A, B & C, D');

        expect($shortened)->toBe('Statuses: A, B & C, D');
    });

    it('leaves non-select filters alone', function () {
        $component = Livewire::test(ListOrdersPhase3::class);
        $filter = $component->instance()->getTable()->getFilter('big');

        expect(callProtected($component, 'shortenTableViewIndicatorLabel', 'big', $filter, 'Big orders'))->toBe('Big orders');
    });
});

describe('pinning', function () {
    it('starts from the developer favorites', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        expect($component->get('tableViewFavoriteFilters'))->toBeNull()
            ->and(callProtected($component, 'effectiveTableViewFavoriteFilters'))->toBe(['status']);
    });

    it('pins in table order and unpins', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big')->call('togglePinnedTableViewFilter', 'multi');

        expect($component->get('tableViewFavoriteFilters'))->toBe(['status', 'multi', 'big']);

        $component->call('togglePinnedTableViewFilter', 'status');

        expect($component->get('tableViewFavoriteFilters'))->toBe(['multi', 'big'])
            ->and(chipNames($component->html()))->toBe(['multi', 'big']);
    });

    it('stores null again when the list equals the developer favorites', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big')->call('togglePinnedTableViewFilter', 'big');

        expect($component->get('tableViewFavoriteFilters'))->toBeNull();
    });

    it('can unpin everything', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'status');

        expect($component->get('tableViewFavoriteFilters'))->toBe([])
            ->and(callProtected($component, 'effectiveTableViewFavoriteFilters'))->toBe([]);
    });

    it('ignores unknown and malformed names', function (mixed $name) {
        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', $name);

        expect($component->get('tableViewFavoriteFilters'))->toBeNull();
    })->with(['unknown' => 'nope', 'empty' => '', 'array' => [['status']], 'int' => 3, 'null' => null, 'bool' => false, 'huge' => str_repeat('x', 5000)]);

    it('ignores a hidden filter', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'secret');

        expect($component->get('tableViewFavoriteFilters'))->toBeNull();
    });

    it('cannot be set by the browser', function () {
        expect(fn () => Livewire::test(ListOrdersPhase3::class)->set('tableViewFavoriteFilters', ['big']))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('does nothing when quick filters are off', function () {
        config(['tablekit.quick_filters.enabled' => false]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big');

        expect($component->get('tableViewFavoriteFilters'))->toBeNull();
    });

    it('survives a new page load through the session', function () {
        Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big');

        $again = Livewire::test(ListOrdersPhase3::class);

        expect($again->get('tableViewFavoriteFilters'))->toBe(['status', 'big'])
            ->and(chipNames($again->html()))->toBe(['status', 'big']);
    });

    it('forgets the session entry when the list is the default again', function () {
        Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big')->call('togglePinnedTableViewFilter', 'big');

        expect(Livewire::test(ListOrdersPhase3::class)->get('tableViewFavoriteFilters'))->toBeNull();
    });

    it('drops filters from the session list that no longer exist', function () {
        $component = Livewire::test(ListOrdersPhase3::class);
        $key = callProtected($component, 'getTableViewSessionKey', 'favorite_filters');

        session()->put($key, ['gone', 'big', ['x'], 5]);

        expect(Livewire::test(ListOrdersPhase3::class)->get('tableViewFavoriteFilters'))->toBe(['big']);
    });

    it('ignores a session value that is not a list', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        session()->put(callProtected($component, 'getTableViewSessionKey', 'favorite_filters'), 'oops');

        expect(Livewire::test(ListOrdersPhase3::class)->get('tableViewFavoriteFilters'))->toBeNull();
    });
});

describe('the favorite macro', function () {
    it('marks a filter and accepts a closure', function () {
        $always = Filter::make('a')->favorite();
        $never = Filter::make('b')->favorite(false);
        $closure = SelectFilter::make('c')->favorite(fn (): bool => true);
        $plain = Filter::make('d');

        expect(FavoriteFilters::isFavorite($always))->toBeTrue()
            ->and(FavoriteFilters::isFavorite($never))->toBeFalse()
            ->and(FavoriteFilters::isFavorite($closure))->toBeTrue()
            ->and(FavoriteFilters::isFavorite($plain))->toBeFalse();
    });

    it('returns the filter for chaining', function () {
        $filter = Filter::make('a');

        expect($filter->favorite())->toBe($filter);
    });

    it('is registered on the base filter class', function () {
        expect(BaseFilter::hasMacro('favorite'))->toBeTrue()->and(BaseFilter::hasMacro('quickFilterFavorite'))->toBeTrue();
    });

    it('has a plugin-scoped name that marks a filter too', function () {
        $filter = Filter::make('a');

        expect($filter->quickFilterFavorite())->toBe($filter)
            ->and(FavoriteFilters::isFavorite($filter))->toBeTrue()
            ->and(FavoriteFilters::isFavorite(Filter::make('b')->quickFilterFavorite(false)))->toBeFalse()
            ->and(FavoriteFilters::isFavorite(SelectFilter::make('c')->quickFilterFavorite(fn (): bool => true)))->toBeTrue();
    });

    describe('when another package owns favorite()', function () {
        function registerFavoriteMacro(bool $forgetOwnership = true): void
        {
            $provider = app()->getProvider(TableKitServiceProvider::class);

            if ($forgetOwnership) {
                (new ReflectionProperty(TableKitServiceProvider::class, 'favoriteMacroRegistered'))->setValue(null, false);
            }

            (new ReflectionMethod($provider, 'registerFavoriteFilterMacro'))->invoke($provider);
        }

        afterEach(function () {
            BaseFilter::flushMacros();
            registerFavoriteMacro();
        });

        it('logs a warning, leaves the other macro alone and keeps quickFilterFavorite working', function () {
            BaseFilter::flushMacros();
            BaseFilter::macro('favorite', fn (): string => 'foreign');

            Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'quickFilterFavorite()'));

            registerFavoriteMacro();

            $filter = Filter::make('a');

            expect($filter->favorite())->toBe('foreign')
                ->and(FavoriteFilters::isFavorite($filter))->toBeFalse()
                ->and(FavoriteFilters::isFavorite(Filter::make('b')->quickFilterFavorite()))->toBeTrue();
        });

        it('stays quiet when the macro is the plugin\'s own', function () {
            Log::shouldReceive('warning')->never();

            registerFavoriteMacro(forgetOwnership: false);
            registerFavoriteMacro(forgetOwnership: false);

            expect(FavoriteFilters::isFavorite(Filter::make('a')->favorite()))->toBeTrue();
        });
    });
});

describe('switches', function () {
    it('is off per table', function () {
        $html = Livewire::test(ListOrdersPhase3Off::class)->set('tableFilters.status.value', 'paid')->html();

        expect($html)->toContain('fi-ta-filter-indicators')->not->toContain('fi-tablekit-quick-filters');
    });

    it('can be turned on by the plugin', function () {
        config(['tablekit.quick_filters.enabled' => false]);

        TableKitPlugin::get()->quickFilters();

        expect(Livewire::test(ListOrders::class)->set('tableFilters.status.value', 'paid')->html())->toContain('fi-tablekit-quick-filters');
    });

    it('can be turned off by the plugin though config says on', function () {
        TableKitPlugin::get()->quickFilters(false);

        expect(Livewire::test(ListOrders::class)->set('tableFilters.status.value', 'paid')->html())->not->toContain('fi-tablekit-quick-filters');
    });
});
