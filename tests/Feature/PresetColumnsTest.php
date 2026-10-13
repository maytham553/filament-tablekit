<?php

use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersColumnGroupsPresets;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersDottedColumnPresets;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersReorderableColumnsPresets;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

it('shows the listed toggleable columns and hides the others, inside column groups too', function () {
    presets(fn () => ['p' => PresetTableView::make()->label('P')->favorite()->defaultToggledColumns(['status'])]);

    $component = Livewire::test(ListOrdersColumnGroupsPresets::class)->call('applyTablePresetView', 'p');

    expect(toggles($component))->toBe(['amount' => false, 'status' => true, 'notes' => false]);
});

it('keeps the column order the user has, while a preset only changes visibility', function () {
    presets(fn () => [
        'p' => PresetTableView::make()->label('P')->favorite()->defaultToggledColumns(['amount']),
        'plain' => PresetTableView::make()->label('Plain')->favorite()->defaultSearch('ORD'),
    ]);

    $component = Livewire::test(ListOrdersReorderableColumnsPresets::class);
    $component->call('applyTableColumnManager', array_reverse($component->get('tableColumns')));

    $order = fn ($c) => array_column($c->get('tableColumns'), 'name');
    $before = $order($component);

    expect($before)->toBe(['notes', 'amount', 'status', 'reference']);

    $component->call('applyTablePresetView', 'p');
    expect($order($component))->toBe($before)->and(toggles($component))->toBe(['notes' => false, 'amount' => true, 'status' => false]);

    $component->call('applyTablePresetView', 'plain');
    expect($order($component))->toBe($before)->and(toggles($component))->toBe(['notes' => false, 'amount' => true, 'status' => true]);
});

it('matches column names that contain a dot', function () {
    presets(fn () => ['p' => PresetTableView::make()->label('P')->favorite()->defaultToggledColumns(['notes'])]);

    $component = Livewire::test(ListOrdersDottedColumnPresets::class)->call('applyTablePresetView', 'p');

    expect(toggles($component))->toBe(['team.name' => false, 'notes' => true]);

    $component->call('applyTablePresetView', 'p');

    presets(fn () => ['q' => PresetTableView::make()->label('Q')->favorite()->defaultToggledColumns(['team.name'])]);

    $other = Livewire::test(ListOrdersDottedColumnPresets::class)->call('applyTablePresetView', 'q');

    expect(toggles($other))->toBe(['team.name' => true, 'notes' => false]);
});

it('treats an empty list as every toggleable column hidden', function () {
    presets(fn () => ['p' => PresetTableView::make()->label('P')->favorite()->defaultToggledColumns([])]);

    $component = Livewire::test(ListOrdersDottedColumnPresets::class)->call('applyTablePresetView', 'p');

    expect(toggles($component))->toBe(['team.name' => false, 'notes' => false]);
});
