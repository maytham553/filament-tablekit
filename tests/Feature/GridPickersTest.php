<?php

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use TableKit\Forms\ColorGridPicker;
use TableKit\Forms\IconGridPicker;
use TableKit\Models\TableView;
use TableKit\Support\ColorPalette;
use TableKit\Support\IconCatalog;
use TableKit\Support\TableKitOptions;
use TableKit\TableKitPlugin;
use TableKit\TableKitServiceProvider;
use TableKit\Tests\Fixtures\Pages\ListOrders;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

/**
 * The radiogroup of one picker, so a test only counts its own cells.
 */
function pickerHtml(string $html, string $kind): string
{
    $start = strpos($html, 'data-tv-picker="'.$kind.'"');

    expect($start)->not->toBeFalse("no {$kind} picker rendered");

    $next = strpos($html, 'data-tv-picker="', $start + 10);

    return substr($html, $start, ($next === false ? strlen($html) : $next) - $start);
}

/**
 * @return array<int, array{value: string, checked: string}>
 */
function pickerCells(string $html, string $kind): array
{
    preg_match_all('/<button[^>]*role="radio"[^>]*>/', pickerHtml($html, $kind), $tags);

    return array_map(function (string $tag): array {
        preg_match('/data-value="([^"]*)"/', $tag, $value);
        preg_match('/aria-checked="([^"]*)"/', $tag, $checked);

        return ['value' => $value[1], 'checked' => $checked[1]];
    }, $tags[0]);
}

/**
 * What FilamentColor stores for a registered palette.
 *
 * @param  array<int, string>  $palette
 * @return array<int, string>
 */
function processedPalette(array $palette): array
{
    return array_map(fn (string $shade): string => Color::convertToOklch($shade), $palette);
}

function quickSaveModal(): string
{
    return Livewire::test(ListOrders::class)->mountAction('quickSaveTableView')->getMountedActionModalHtml();
}

describe('rendering', function () {
    it('renders colors and icons as radio grids, not selects', function () {
        $html = quickSaveModal();

        expect($html)->toContain('role="radiogroup"')
            ->and(substr_count($html, 'role="radiogroup"'))->toBe(2)
            ->and(pickerHtml($html, 'color'))->not->toContain('<select')
            ->and(preg_match('/<select[^>]*mountedActionsData/', $html))->toBe(0)
            ->and(array_column(pickerCells($html, 'color'), 'value'))->toBe(config('tablekit.colors'))
            ->and(array_column(pickerCells($html, 'icon'), 'value'))->toBe(['', ...config('tablekit.icons')]);
    });

    it('puts the no icon cell first and labels every cell with a tooltip and an accessible name', function () {
        $html = quickSaveModal();

        expect(pickerCells($html, 'icon')[0]['value'])->toBe('');

        foreach (['color', 'icon'] as $kind) {
            $picker = pickerHtml($html, $kind);

            preg_match_all('/<button[^>]*role="radio"[^>]*>/', $picker, $tags);

            foreach ($tags[0] as $tag) {
                expect($tag)->toMatch('/title="[^"]+"/')->toMatch('/aria-label="[^"]+"/')->toContain('type="button"');
            }
        }

        expect(pickerHtml($html, 'color'))->toContain('title="Danger red"')
            ->and(pickerHtml($html, 'icon'))->toContain('title="No icon"')->toContain('title="Star"');
    });

    it('renders real icons and swatch colors in the cells', function () {
        $html = quickSaveModal();
        $icons = pickerHtml($html, 'icon');
        $colors = pickerHtml($html, 'color');

        expect(substr_count($icons, '<svg'))->toBeGreaterThanOrEqual(count(config('tablekit.icons')))
            ->and($colors)->toContain('--color-500:var(--rose-500)')->toContain('--color-500:var(--primary-500)')
            ->and($icons)->toContain('data-tv-search')->toContain('x-model="search"');
    });

    it('lays out as a scrollable auto-fill grid with logical properties', function () {
        $html = quickSaveModal();

        expect($html)->toContain('grid-template-columns: repeat(auto-fill')
            ->toContain('max-height: 14rem')->toContain('overflow-y: auto')
            ->toContain('margin-block-end')->not->toContain('margin-left')->not->toContain('padding-right');
    });

    it('marks the default color selected and no icon selected on Quick Save', function () {
        $html = quickSaveModal();
        $colors = pickerCells($html, 'color');
        $icons = pickerCells($html, 'icon');

        expect(array_column(array_filter($colors, fn ($c) => $c['checked'] === 'true'), 'value'))->toBe(['primary'])
            ->and(array_column(array_filter($icons, fn ($c) => $c['checked'] === 'true'), 'value'))->toBe(['']);
    });

    it('marks the stored color and icon selected when editing', function () {
        $view = storeView($this->user->id, [], ['name' => 'Mine', 'color' => 'rose', 'icon' => 'trophy']);

        $html = Livewire::test(ListOrders::class)->call('openTableViewsManager')
            ->mountAction('editTableView', ['viewId' => $view->id])->getMountedActionModalHtml();

        expect(array_column(array_filter(pickerCells($html, 'color'), fn ($c) => $c['checked'] === 'true'), 'value'))->toBe(['rose'])
            ->and(array_column(array_filter(pickerCells($html, 'icon'), fn ($c) => $c['checked'] === 'true'), 'value'))->toBe(['trophy']);
    });

    it('keeps exactly one tab stop per group (roving tabindex)', function () {
        $html = quickSaveModal();

        foreach (['color', 'icon'] as $kind) {
            expect(substr_count(pickerHtml($html, $kind), 'tabindex="0"'))->toBe(1);
        }

        $view = storeView($this->user->id, [], ['name' => 'Mine', 'color' => 'teal', 'icon' => 'bell']);
        $edit = Livewire::test(ListOrders::class)->call('openTableViewsManager')->mountAction('editTableView', ['viewId' => $view->id])->getMountedActionModalHtml();

        expect(pickerHtml($edit, 'color'))->toMatch('/data-value="teal"[^>]*tabindex="0"/');
    });

    it('wires keyboard handling for radiogroup navigation', function () {
        $html = quickSaveModal();

        expect($html)->toContain('x-on:keydown="onKey($event)"')
            ->toContain("'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'")
            ->toContain("=== 'rtl'")
            ->toContain('x-on:click="choose(');
    });

    it('offers only the plugin configured colors and icons', function () {
        TableKitPlugin::get()->colors(['teal', 'rose'])->icons(['star']);

        $html = quickSaveModal();

        expect(array_column(pickerCells($html, 'color'), 'value'))->toBe(['teal', 'rose'])
            ->and(array_column(pickerCells($html, 'icon'), 'value'))->toBe(['', 'star']);
    });

    it('renders the icons through the alias, so a remap shows up', function () {
        FilamentIcon::register(['tablekit::icons.star' => new HtmlString('<svg data-test="remapped-star"></svg>')]);

        expect(pickerHtml(quickSaveModal(), 'icon'))->toContain('data-test="remapped-star"');
    });

    it('renders arabic labels and tooltips', function () {
        app()->setLocale('ar');

        $html = quickSaveModal();

        expect(pickerHtml($html, 'color'))->toContain('title="أحمر"')
            ->and(pickerHtml($html, 'icon'))->toContain('title="بدون أيقونة"')->toContain('title="نجمة"')
            ->and($html)->toContain('placeholder="ابحث عن أيقونة"');
    });
});

describe('icon search', function () {
    it('gives every cell search text with its name, label and keywords', function () {
        $items = collect((new IconGridPicker('icon'))->icons(['star', 'currency'])->getItems())->keyBy('value');

        expect($items['star']['search'])->toContain('star')->toContain('favorite')
            ->and($items['currency']['search'])->toContain('money')->toContain('cash')
            ->and($items['']['search'])->toContain('no icon');
    });

    it('searches arabic keywords and still matches english ones in the arabic locale', function () {
        app()->setLocale('ar');

        $star = collect((new IconGridPicker('icon'))->icons(['star'])->getItems())->firstWhere('value', 'star');

        expect($star['search'])->toContain('نجمة')->toContain('مفضل')->toContain('favorite');
    });

    it('ships the search box, filter hooks and the empty message', function () {
        $html = quickSaveModal();

        expect($html)->toContain('placeholder="Search icons"')
            ->toContain('x-show="matches($el.dataset.value)"')
            ->toContain('No icons match your search.')
            ->toContain('x-on:keydown.enter.prevent');
    });

    it('sends the haystack to the browser for every offered icon', function () {
        $html = quickSaveModal();

        $q = '\\'.'u0022';

        foreach (['star', 'trophy', 'credit-card', 'map-pin'] as $key) {
            expect($html)->toContain("{$q}v{$q}:{$q}{$key}{$q}");
        }
    });
});

describe('validation', function () {
    it('rejects hostile colors and icons without an error page', function (mixed $color, mixed $icon) {
        $component = Livewire::test(ListOrders::class);

        $component->callAction('quickSaveTableView', ['name' => 'X', 'color' => $color, 'icon' => $icon])
            ->assertHasActionErrors();

        expect(TableView::count())->toBe(0);
    })->with([
        'unknown color' => ['hotpink', null],
        'html color' => ['<script>alert(1)</script>', null],
        'css injection color' => ['red;background:url(x)', null],
        'int color' => [123, null],
        'bool color' => [true, null],
        'long color' => [str_repeat('a', 5000), null],
        'padded color' => ['primary ', null],
        'upper color' => ['PRIMARY', null],
        'unknown icon' => ['primary', 'not-an-icon'],
        'raw heroicon' => ['primary', 'heroicon-o-star'],
        'html icon' => ['primary', '"><img src=x>'],
        'path icon' => ['primary', '../../etc/passwd'],
        'long icon' => ['primary', str_repeat('a', 5000)],
        'padded icon' => ['primary', 'star '],
    ]);

    it('rejects hostile values on edit too', function () {
        $view = storeView($this->user->id, [], ['name' => 'Mine', 'color' => 'primary']);
        foreach ([['hotpink', null], ['primary', 'nope']] as [$color, $icon]) {
            Livewire::test(ListOrders::class)->call('openTableViewsManager')->callAction('editTableView', ['name' => 'Mine', 'color' => $color, 'icon' => $icon], ['viewId' => $view->id])
                ->assertHasActionErrors();
        }

        expect($view->fresh()->color)->toBe('primary');
    });

    it('accepts the first, middle and last configured color and icon, and offers all of them to validation', function () {
        $colors = config('tablekit.colors');
        $icons = config('tablekit.icons');
        $component = Livewire::test(ListOrders::class);

        foreach ([$colors[0], $colors[intdiv(count($colors), 2)], end($colors)] as $i => $color) {
            expect(quickSave($component, "Color {$i}", ['color' => $color])->color)->toBe($color);
        }

        foreach ([$icons[0], $icons[intdiv(count($icons), 2)], end($icons)] as $i => $icon) {
            expect(quickSave($component, "Icon {$i}", ['icon' => $icon])->icon)->toBe($icon);
        }

        expect(TableKitOptions::resolve()->colors())->toBe($colors)
            ->and(TableKitOptions::resolve()->icons())->toBe($icons);
    });

    it('does not blow up rendering when the stored state is not a string', function () {
        $component = Livewire::test(ListOrders::class)->mountAction('quickSaveTableView');

        foreach (['mountedActions.0.data.color', 'mountedActions.0.data.icon'] as $path) {
            $component->set($path, ['not', 'a', 'string'])->assertOk();
        }

        expect($component->getMountedActionModalHtml())->toContain('data-tv-picker="icon"');
    });

    it('treats an empty icon as none', function () {
        expect(quickSave(Livewire::test(ListOrders::class), 'No icon', ['icon' => null])->icon)->toBeNull();
    });
});

describe('saving', function () {
    it('stores the chosen keys through Quick Save and Edit', function () {
        $saved = quickSave(Livewire::test(ListOrders::class), 'Picked', ['color' => 'emerald', 'icon' => 'credit-card']);

        expect([$saved->color, $saved->icon])->toBe(['emerald', 'credit-card']);

        Livewire::test(ListOrders::class)->call('openTableViewsManager')
            ->callAction('editTableView', ['name' => 'Picked', 'color' => 'violet', 'icon' => null], ['viewId' => $saved->id])
            ->assertHasNoActionErrors();

        expect([$saved->fresh()->color, $saved->fresh()->icon])->toBe(['violet', null]);
    });

    it('keeps views saved with the old color names working', function () {
        foreach (['primary', 'gray', 'success', 'warning', 'danger', 'info'] as $color) {
            expect(storeView($this->user->id, [], ['color' => $color])->resolvedColor())->toBe($color);
        }

        expect(storeView($this->user->id, [], ['color' => 'not-a-color'])->resolvedColor())->toBe('primary');
    });
});

describe('colors', function () {
    it('registers the extra palette with Filament', function () {
        $known = FilamentColor::getColors();

        foreach (ColorPalette::EXTRA as $name) {
            expect($known)->toHaveKey($name);
        }

        expect($known['rose'])->toBe(processedPalette(Color::Rose));
    });

    it('lets a later registration of the app win over the plugin palette', function () {
        FilamentColor::register(['red' => Color::Pink]);

        expect(FilamentColor::getColors()['red'])->toBe(processedPalette(Color::Pink));
    });

    it('registers the palette before the app boots, so app and panel colors win', function () {
        $previous = Container::getInstance();

        $app = new class(sys_get_temp_dir()) extends Application
        {
            public array $capturedBooting = [];

            public function isBooted(): bool
            {
                return false;
            }

            public function booting($callback): void
            {
                $this->capturedBooting[] = $callback;
            }
        };

        try {
            (new TableKitServiceProvider($app))->packageRegistered();
        } finally {
            Container::setInstance($previous);
            Facade::setFacadeApplication($previous);
        }

        expect($app->capturedBooting)->toHaveCount(1);
    });

    it('renders every configured color on the favorites bar and the manager rows', function () {
        foreach (config('tablekit.colors') as $i => $color) {
            storeView($this->user->id, [], ['name' => "View {$i}", 'color' => $color, 'sort' => $i]);
        }

        $bar = Livewire::test(ListOrders::class)->html();
        $manager = Livewire::test(ListOrders::class)->call('openTableViewsManager')->html();

        foreach (config('tablekit.colors') as $color) {
            expect(FilamentColor::getColors())->toHaveKey($color);
            // Filament draws a gray button without a color class.
            expect($bar)->toContain($color === 'gray' ? 'fi-outlined' : "fi-color-{$color}");
            expect($manager)->toContain("background-color: var(--{$color}-500)");
        }
    });

    it('drops a configured color Filament does not know', function () {
        TableKitPlugin::get()->colors(['primary', 'nope', 'rose']);

        expect(TableKitOptions::resolve()->colors())->toBe(['primary', 'rose']);
    });
});

describe('icons', function () {
    it('resolves every configured icon key to an icon', function () {
        foreach (config('tablekit.icons') as $key) {
            expect(FilamentIcon::resolve("tablekit::icons.{$key}"))->toBeInstanceOf(Heroicon::class, $key);
        }
    });

    it('offers a rich, unique, ordered set', function () {
        $icons = config('tablekit.icons');

        expect(count($icons))->toBeGreaterThanOrEqual(80)->toBeLessThanOrEqual(130)
            ->and($icons)->toBe(array_values(array_unique($icons)))
            ->and(array_diff($icons, array_keys(IconCatalog::ICONS)))->toBe([]);
    });

    it('keeps every key that existed before', function () {
        $legacy = [
            'star', 'heart', 'bookmark', 'flag', 'bolt', 'fire', 'funnel', 'clock', 'user', 'users', 'tag', 'eye', 'chart-bar',
            'currency', 'check-circle', 'warning', 'folder', 'inbox', 'truck', 'calendar', 'shopping-cart', 'archive', 'document',
            'bell', 'home', 'globe',
        ];

        expect(array_diff($legacy, array_keys(IconCatalog::ICONS)))->toBe([])
            ->and(array_diff($legacy, config('tablekit.icons')))->toBe([]);
    });

    it('renders every catalog icon through the blade icon sets', function () {
        foreach (IconCatalog::ICONS as $key => $icon) {
            expect(Blade::render('<x-filament::icon :icon="$icon" />', ['icon' => $icon]))->toContain('<svg');
        }
    });

    it('drops a configured key that has no alias', function () {
        TableKitPlugin::get()->icons(['star', 'unregistered-key']);

        expect(TableKitOptions::resolve()->icons())->toBe(['star']);
    });

    it('never hard codes a heroicon in the picker', function () {
        expect(file_get_contents(__DIR__.'/../../resources/views/forms/grid-picker.blade.php'))->not->toContain('heroicon');

        foreach (glob(__DIR__.'/../../src/Forms/*.php') as $file) {
            expect(file_get_contents($file))->not->toContain('heroicon', basename($file));
        }
    });
});

describe('field classes', function () {
    it('exposes the item shape', function () {
        $picker = (new ColorGridPicker('color'))->colors(['primary', 'rose']);

        expect($picker->getKind())->toBe('color')
            ->and($picker->getItems())->toHaveCount(2)
            ->and($picker->getItems()[1])->toMatchArray(['value' => 'rose', 'label' => 'Rose', 'none' => false]);
    });

    it('ignores non string keys', function () {
        expect((new IconGridPicker('icon'))->icons(['star', 5, null, ['x'], 'star'])->getKeys())->toBe(['star']);
    });
});
