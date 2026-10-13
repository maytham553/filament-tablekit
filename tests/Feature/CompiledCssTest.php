<?php

use Filament\FilamentServiceProvider;
use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Presets;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;

/**
 * Filament 3's default theme is a compiled stylesheet: a Tailwind class it does not contain does nothing, so the plugin
 * must use only classes found in it and write everything else as inline styles.
 *
 * @return array<string, true>
 */
function shippedClasses(): array
{
    $root = dirname((new ReflectionClass(FilamentServiceProvider::class))->getFileName(), 3);
    $css = '';

    foreach (['filament/dist/theme.css', 'forms/dist/index.css', 'support/dist/index.css'] as $file) {
        $css .= file_get_contents("{$root}/{$file}");
    }

    preg_match_all('/\.((?:\\\\.|[A-Za-z0-9_-])+)/', $css, $matches);

    return array_fill_keys(array_map(fn (string $name): string => preg_replace('/\\\\(.)/', '$1', $name), $matches[1]), true);
}

/**
 * @return array<int, string>
 */
function classesMissingFromCss(string $html): array
{
    $shipped = shippedClasses();
    preg_match_all('/\sclass="([^"]*)"/', html_entity_decode($html), $matches);

    return collect($matches[1])
        ->flatMap(fn (string $value): array => preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY))
        ->reject(fn (string $class): bool => isset($shipped[$class]) || preg_match('/^(fi-|tv-|tablekit|x-)/', $class) || preg_match('/[\[{}$]/', $class))
        ->unique()
        ->values()
        ->all();
}

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
    config(['tablekit.quick_filters.enabled' => true]);

    ListOrdersWithPresets::$presets = fn () => [
        'a' => PresetTableView::make()->label('Alpha preset')->favorite()->color('success'),
        'b' => PresetTableView::make()->label('Beta preset'),
    ];
    ListOrdersPhase3Presets::$presets = ListOrdersWithPresets::$presets;

    storeView($this->user->id, [], ['name' => 'Fav view', 'sort' => 1, 'resource' => ListOrdersWithPresets::class]);
    storeView($this->user->id, [], ['name' => 'Plain view', 'sort' => 2, 'is_favorite' => false, 'resource' => ListOrdersWithPresets::class]);
});

it('uses only classes that exist in the compiled Filament theme in the view sources', function () {
    $shipped = shippedClasses();
    $missing = [];

    foreach (glob(__DIR__.'/../../resources/views/{,*/}*.blade.php', GLOB_BRACE) as $file) {
        $source = preg_replace('/\{\{.*?\}\}|@\w+(\([^)]*\))?/s', ' ', file_get_contents($file));
        $source = preg_replace('/<style>.*?<\/style>/s', '', $source);
        preg_match_all('/\sclass="([^"]*)"/', $source, $matches);

        foreach (preg_split('/\s+/', implode(' ', $matches[1]), -1, PREG_SPLIT_NO_EMPTY) as $class) {
            if (! isset($shipped[$class]) && ! preg_match('/^(fi-|tv-|tablekit|x-)/', $class)) {
                $missing[basename($file)][] = $class;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('renders the bar, the manager, the sort button, the column list and the quick filters with shipped classes only', function () {
    $component = Livewire::test(ListOrdersPhase3Presets::class)
        ->call('addTableViewSort', 'customer_name')
        ->call('addTableViewSort', 'amount')
        ->call('openTableViewsManager');

    $html = $component->html();

    expect($html)->toContain('fi-tablekit-bar')->toContain('fi-tablekit-manager-heading')->toContain('data-column-row')->toContain('data-tablekit-sort-trigger')->toContain('data-quick-filter')
        ->and(classesMissingFromCss($html))->toBe([]);
});
