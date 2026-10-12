<?php

use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;
use Elite\TableViews\Tests\Fixtures\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    config(['cache.default' => 'array']);
    Cache::flush();

    badgeCalls(reset: true);
});

/**
 * @return array<string, int>
 */
function badgeCalls(?string $hit = null, bool $reset = false): array
{
    static $calls = [];

    if ($reset) {
        $calls = ['default' => 0, 'a' => 0, 'b' => 0, 'hidden' => 0];
    }

    if ($hit !== null) {
        $calls[$hit]++;
    }

    return $calls;
}

function countingBadge(string $name, mixed $value = 5): Closure
{
    return function () use ($name, $value) {
        badgeCalls($name);

        return $value;
    };
}

it('runs each badge closure once per request, however often the bar is read', function () {
    presets(fn () => [
        'default' => PresetTableView::make()->label('Default')->badge(countingBadge('default')),
        'a' => PresetTableView::make()->label('A')->favorite()->badge(countingBadge('a'))->badgeColor('danger'),
        'b' => PresetTableView::make()->label('B')->favorite()->badge(countingBadge('b'))->tooltip(fn () => 'tip'),
    ]);

    $component = Livewire::test(ListOrdersWithPresets::class);

    expect(badgeCalls())->toBe(['default' => 1, 'a' => 1, 'b' => 1, 'hidden' => 0]);

    badgeCalls(reset: true);

    $component->set('tableSearch', 'ORD-01')->call('applyTablePresetView', 'a');

    expect(badgeCalls())->toBe(['default' => 2, 'a' => 2, 'b' => 2, 'hidden' => 0]);
});

it('does not run the badge of a preset that is not in the bar', function () {
    presets(fn () => [
        'a' => PresetTableView::make()->label('A')->favorite()->badge(countingBadge('a')),
        'hidden' => PresetTableView::make()->label('Not favorite')->badge(countingBadge('hidden')),
    ]);

    Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager');

    expect(badgeCalls()['hidden'])->toBe(0)->and(badgeCalls()['a'])->toBeGreaterThan(0);
});

it('does not run any badge when the favorites bar is off', function () {
    TableViewsPlugin::get()->favoritesBar(false);

    presets(fn () => [
        'default' => PresetTableView::make()->badge(countingBadge('default')),
        'a' => PresetTableView::make()->favorite()->badge(countingBadge('a')),
    ]);

    Livewire::test(ListOrdersWithPresets::class)->set('tableSearch', 'x');

    expect(badgeCalls()['default'] + badgeCalls()['a'])->toBe(0);
});

describe('cacheBadgeFor', function () {
    beforeEach(function () {
        presets(fn () => [
            'a' => PresetTableView::make()->label('A')->favorite()->badge(countingBadge('a', 42))->cacheBadgeFor(60),
            'b' => PresetTableView::make()->label('B')->favorite()->badge(countingBadge('b', 0))->cacheBadgeFor(60),
        ]);
    });

    it('keeps the value across requests', function () {
        $component = Livewire::test(ListOrdersWithPresets::class)->assertSee('42');

        $component->set('tableSearch', 'ORD-01')->set('tableSearch', 'ORD-02')->call('$refresh');

        expect(badgeCalls()['a'])->toBe(1)->and(badgeCalls()['b'])->toBe(1);
    });

    it('caches a zero badge too', function () {
        Livewire::test(ListOrdersWithPresets::class)->call('$refresh')->call('$refresh');

        expect(badgeCalls()['b'])->toBe(1);
    });

    it('is not shared between users', function () {
        Livewire::test(ListOrdersWithPresets::class);

        $this->actingAs(User::create(['name' => 'Second', 'email' => 'second@example.test', 'password' => 'x']));

        Livewire::test(ListOrdersWithPresets::class);

        expect(badgeCalls()['a'])->toBe(2);
    });

    it('lets the closure run again once the cache expires', function () {
        Livewire::test(ListOrdersWithPresets::class);
        Cache::flush();
        Livewire::test(ListOrdersWithPresets::class);

        expect(badgeCalls()['a'])->toBe(2);
    });

    it('does not cache a badge that was not asked to be', function () {
        presets(fn () => ['a' => PresetTableView::make()->label('A')->favorite()->badge(countingBadge('a', 7))]);

        $component = Livewire::test(ListOrdersWithPresets::class);
        $component->call('$refresh');

        expect(badgeCalls()['a'])->toBe(2);
    });
});
