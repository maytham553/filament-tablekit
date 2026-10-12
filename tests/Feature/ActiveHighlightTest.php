<?php

use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function defaultButton(string $html): string
{
    preg_match('/<button[^>]*data-default="true"[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

function presetButton(string $html, string $key): string
{
    preg_match('/<button[^>]*wire:key="table-preset-'.preg_quote($key, '/').'"[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

it('highlights the default button when no view is active', function () {
    presets(fn () => ['a' => PresetTableView::make()->label('A')->favorite()->defaultSearch('ORD-01')]);

    $html = Livewire::test(ListOrdersWithPresets::class)->html();

    expect(defaultButton($html))->toContain('aria-pressed="true"')->toContain('box-shadow')
        ->and(presetButton($html, 'a'))->toContain('aria-pressed="false"')->not->toContain('box-shadow');
});

it('highlights the default button and the preset when the developer default has any key', function (string $key, bool $favorite) {
    presets(fn () => [
        $key => PresetTableView::make()->label('Dev')->favorite($favorite)->default()->defaultSearch('ORD-01'),
        'other' => PresetTableView::make()->label('Other')->favorite()->defaultSearch('ORD-02'),
    ]);

    $component = Livewire::test(ListOrdersWithPresets::class)->assertSet('activeTablePresetKey', $key);
    $html = $component->html();

    expect(defaultButton($html))->toContain('aria-pressed="true"')->toContain('box-shadow')
        ->and(presetButton($html, 'other'))->toContain('aria-pressed="false"');

    if ($favorite && $key !== 'default') {
        expect(presetButton($html, $key))->toContain('aria-pressed="true"')->toContain('data-active="true"')->toContain('box-shadow');
    }

    $html = $component->call('applyTablePresetView', 'other')->html();

    expect(defaultButton($html))->toContain('aria-pressed="false"')->not->toContain('box-shadow')
        ->and(presetButton($html, 'other'))->toContain('aria-pressed="true"')->toContain('box-shadow');
})->with([
    'default key, favorite' => ['default', true],
    'other key, favorite' => ['all', true],
    'other key, not a favorite' => ['overdue', false],
]);

it('gives an active gray preset a visible mark that does not depend on the color', function () {
    presets(fn () => [
        'all' => PresetTableView::make()->label('All')->color('gray')->favorite()->default(),
        'x' => PresetTableView::make()->label('X')->color('gray')->favorite()->defaultSearch('ORD-02'),
    ]);

    $html = Livewire::test(ListOrdersWithPresets::class)->html();

    expect(substr_count($html, 'box-shadow: 0 0 0 2px'))->toBe(2)
        ->and(presetButton($html, 'x'))->not->toContain('box-shadow');
});

it('keeps the default button off while a user view is active', function () {
    $view = storeView($this->user->id, ['search' => 'ORD-03'], ['name' => 'Mine', 'resource' => ListOrdersWithPresets::class]);
    presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->favorite()]);

    $html = Livewire::test(ListOrdersWithPresets::class)->call('applyTableView', $view->id)->html();

    expect(defaultButton($html))->toContain('aria-pressed="false"')
        ->and($html)->toContain('wire:key="table-view-'.$view->id.'"');
});

it('shows the modified mark on the default button when the developer default has another key', function () {
    presets(fn () => ['overdue' => PresetTableView::make()->label('Overdue')->default()->defaultSearch('ORD-01')]);

    $component = Livewire::test(ListOrdersWithPresets::class);

    expect($component->html())->not->toContain(__('table-views::favorites-bar.modified'));

    $component->set('tableSearch', 'ORD-02')->assertSee(__('table-views::favorites-bar.modified'));
});

it('agrees with the manager on which view is active', function () {
    presets(fn () => ['all' => PresetTableView::make()->label('All orders')->color('gray')->favorite()->default()]);

    $component = Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager');
    $html = $component->html();

    preg_match_all('/data-active="true"/', $html, $active);

    // the bar button and the manager row of the same preset, nothing else
    expect($active[0])->toHaveCount(2)
        ->and(presetButton($html, 'all'))->toContain('data-active="true"');
});
