<?php

use Elite\TableViews\Models\TablePreference;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\Support\TableViewCatalog;
use Illuminate\Support\Collection;

function makePresets(array $keys): array
{
    $presets = [];

    foreach ($keys as $key => $favorite) {
        $presets[$key] = PresetTableView::make()->key($key)->label(ucfirst($key))->favorite($favorite);
    }

    return $presets;
}

function makeViews(array $rows): Collection
{
    return collect($rows)->map(fn (array $row, int $index) => (new TableView)->forceFill([
        'id' => $row['id'] ?? $index + 1,
        'name' => $row['name'] ?? 'View '.($index + 1),
        'is_favorite' => $row['favorite'] ?? true,
        'sort' => $row['sort'] ?? $index + 1,
        'color' => 'primary',
    ]));
}

function makePreferences(array $rows): Collection
{
    return collect($rows)->map(fn (array $row) => (new TablePreference)->forceFill([
        'view_type' => 'preset',
        'is_hidden' => false,
        'is_default' => false,
        'is_favorite' => null,
        'sort' => null,
        ...$row,
    ]));
}

function keys($items): array
{
    return $items->pluck('key')->map(fn ($key) => (string) $key)->all();
}

it('keeps the developer order when there are no preferences', function () {
    $catalog = TableViewCatalog::build(makePresets(['a' => true, 'b' => false, 'c' => true]), collect(), collect(), true);

    expect(keys($catalog->presets))->toBe(['a', 'b', 'c'])
        ->and(keys($catalog->favoritePresets()))->toBe(['a', 'c'])
        ->and(keys($catalog->presetViews()))->toBe(['b']);
});

it('orders ranked presets by the users sort and puts new ones after or before them', function () {
    $presets = makePresets(['a' => true, 'b' => true, 'c' => true, 'd' => true]);
    $prefs = makePreferences([['view_key' => 'c', 'sort' => 0], ['view_key' => 'a', 'sort' => 1]]);

    expect(keys(TableViewCatalog::build($presets, collect(), $prefs, true, 'after')->presets))->toBe(['c', 'a', 'b', 'd'])
        ->and(keys(TableViewCatalog::build($presets, collect(), $prefs, true, 'before')->presets))->toBe(['b', 'd', 'c', 'a']);
});

it('breaks sort ties by developer order', function () {
    $presets = makePresets(['a' => true, 'b' => true, 'c' => true]);
    $prefs = makePreferences([['view_key' => 'c', 'sort' => 1], ['view_key' => 'a', 'sort' => 1]]);

    expect(keys(TableViewCatalog::build($presets, collect(), $prefs, true)->presets))->toBe(['a', 'c', 'b']);
});

it('uses a stored favorite choice over the developer default and null means the developer default', function () {
    $presets = makePresets(['a' => true, 'b' => false, 'c' => true]);
    $prefs = makePreferences([
        ['view_key' => 'a', 'is_favorite' => false],
        ['view_key' => 'b', 'is_favorite' => true],
        ['view_key' => 'c', 'is_favorite' => null],
    ]);

    $catalog = TableViewCatalog::build($presets, collect(), $prefs, true);

    expect(keys($catalog->favoritePresets()))->toBe(['b', 'c'])
        ->and(keys($catalog->presetViews()))->toBe(['a']);
});

it('ignores stored order and favorites when presets are not manageable', function () {
    $presets = makePresets(['a' => true, 'b' => false]);
    $prefs = makePreferences([['view_key' => 'b', 'sort' => 0, 'is_favorite' => true], ['view_key' => 'a', 'sort' => 1, 'is_favorite' => false]]);

    $catalog = TableViewCatalog::build($presets, collect(), $prefs, false);

    expect(keys($catalog->presets))->toBe(['a', 'b'])
        ->and(keys($catalog->favoritePresets()))->toBe(['a']);
});

it('keeps the default marker even when presets are not manageable', function () {
    $catalog = TableViewCatalog::build(makePresets(['a' => true]), collect(), makePreferences([['view_key' => 'a', 'is_default' => true]]), false);

    expect($catalog->defaultItem()->key)->toBe('a');
});

it('drops presets the user hid and the preset keyed default', function () {
    $presets = makePresets(['default' => true, 'a' => true, 'b' => true]);
    $prefs = makePreferences([['view_key' => 'b', 'is_hidden' => true]]);

    expect(keys(TableViewCatalog::build($presets, collect(), $prefs, true)->presets))->toBe(['a']);
});

it('keeps the order and the favorite flag of user views from the views table', function () {
    $views = makeViews([
        ['id' => 1, 'sort' => 5],
        ['id' => 2, 'sort' => 6, 'favorite' => false],
        ['id' => 3, 'sort' => 7],
        ['id' => 4, 'sort' => 8, 'favorite' => false],
    ]);

    $catalog = TableViewCatalog::build([], $views, collect(), true);

    expect(keys($catalog->favoriteUserViews()))->toBe(['1', '3'])
        ->and(keys($catalog->myViews()))->toBe(['2', '4']);
});

it('does not let a preference row change the favorite or order of a user view', function () {
    $views = makeViews([['id' => 1, 'sort' => 1], ['id' => 2, 'sort' => 2, 'favorite' => false]]);
    $prefs = makePreferences([['view_type' => 'user', 'view_key' => '1', 'is_favorite' => false, 'sort' => 99]]);

    $catalog = TableViewCatalog::build([], $views, $prefs, true);

    expect(keys($catalog->favoriteUserViews()))->toBe(['1'])
        ->and(keys($catalog->userViews))->toBe(['1', '2']);
});

it('builds the three manager sections in the specified order', function () {
    $presets = makePresets(['p1' => true, 'p2' => false]);
    $views = makeViews([['id' => 1], ['id' => 2, 'favorite' => false]]);

    $catalog = TableViewCatalog::build($presets, $views, collect(), true);

    expect(keys($catalog->favorites()))->toBe(['p1', '1'])
        ->and(keys($catalog->myViews()))->toBe(['2'])
        ->and(keys($catalog->presetViews()))->toBe(['p2']);
});

it('finds items by type and key and marks the default', function () {
    $presets = makePresets(['a' => true]);
    $views = makeViews([['id' => 7]]);
    $prefs = makePreferences([['view_type' => 'user', 'view_key' => '7', 'is_default' => true]]);

    $catalog = TableViewCatalog::build($presets, $views, $prefs, true);

    expect($catalog->find('preset', 'a')->label)->toBe('A')
        ->and($catalog->find('user', '7')->isDefault)->toBeTrue()
        ->and($catalog->find('user', 'a'))->toBeNull()
        ->and($catalog->find('preset', '7'))->toBeNull()
        ->and($catalog->find('weird', 'a'))->toBeNull()
        ->and($catalog->defaultItem()->key)->toBe('7')
        ->and($catalog->count())->toBe(2);
});

it('resolves an unknown color to primary', function () {
    $presets = ['a' => PresetTableView::make()->key('a')->color('not-a-color'), 'b' => PresetTableView::make()->key('b')->color('success')];

    $catalog = TableViewCatalog::build($presets, collect(), collect(), true);

    expect($catalog->find('preset', 'a')->color)->toBe('primary')
        ->and($catalog->find('preset', 'b')->color)->toBe('success');
});
