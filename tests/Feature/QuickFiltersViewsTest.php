<?php

use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Presets;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
    config(['tablekit.quick_filters.enabled' => true]);
});

function favoritesOf($component): array
{
    return callProtected($component, 'effectiveTableViewFavoriteFilters');
}

describe('saving and applying', function () {
    it('stores no favorites while they are the developer defaults', function () {
        $state = quickSave(Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid'), 'Plain')->state->toArray();

        expect($state)->not->toHaveKey('favoriteFilters');
    });

    it('stores the pinned filters once they differ and brings them back', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big')->call('togglePinnedTableViewFilter', 'status');
        $view = quickSave($component, 'Pinned');

        expect($view->state->toArray()['favoriteFilters'])->toBe(['big']);

        session()->flush();

        $again = Livewire::test(ListOrdersPhase3::class);

        expect(favoritesOf($again))->toBe(['status'])
            ->and($again->call('applyTableView', $view->id)->get('tableViewFavoriteFilters'))->toBe(['big'])
            ->and(callProtected($again, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('stores an explicit empty list', function () {
        $view = quickSave(Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'status'), 'None');

        expect($view->state->toArray()['favoriteFilters'])->toBe([])
            ->and(Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id)->get('tableViewFavoriteFilters'))->toBe([]);
    });

    it('stores a pinned list that equals the defaults as the defaults', function () {
        $view = quickSave(Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big')->call('togglePinnedTableViewFilter', 'big'), 'Same');

        expect($view->state->toArray())->not->toHaveKey('favoriteFilters');
    });

    it('resets pins to the defaults when an older view without the key is applied', function () {
        $view = storeView($this->user->id, ['filters' => ['status' => ['value' => 'paid']]], ['resource' => ListOrdersPhase3::class]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big')->call('applyTableView', $view->id);

        expect($component->get('tableViewFavoriteFilters'))->toBeNull()
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('drops filters that no longer exist and keeps table order', function () {
        $view = storeView($this->user->id, ['favoriteFilters' => ['big', 'gone', 'secret', 'multi', 5, ['x']]], ['resource' => ListOrdersPhase3::class]);

        expect(Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id)->get('tableViewFavoriteFilters'))->toBe(['multi', 'big']);
    });

    it('marks a view modified when a pin changes', function () {
        $view = quickSave(Livewire::test(ListOrdersPhase3::class)->set('tableFilters.status.value', 'paid'), 'V');

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();

        $component->call('togglePinnedTableViewFilter', 'big');

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeTrue();

        $component->call('togglePinnedTableViewFilter', 'big');

        expect(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('ignores the stored pins when quick filters are off', function () {
        $view = storeView($this->user->id, ['favoriteFilters' => ['big']], ['resource' => ListOrdersPhase3::class]);

        config(['tablekit.quick_filters.enabled' => false]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('applyTableView', $view->id);

        expect($component->get('tableViewFavoriteFilters'))->toBeNull()
            ->and(quickSave($component, 'Off')->state->toArray())->not->toHaveKey('favoriteFilters');
    });

    it('restores the pins and the filters together', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->set('tableFilters.big.isActive', true)->call('togglePinnedTableViewFilter', 'multi');
        $view = quickSave($component, 'Both');

        $again = Livewire::test(ListOrdersPhase3::class)->call('removeTableFilters')->call('applyTableView', $view->id);

        expect($again->get('tableFilters.big.isActive'))->toBeTrue()
            ->and($again->get('tableViewFavoriteFilters'))->toBe(['status', 'multi']);
    });
});

describe('Default and presets', function () {
    it('returns to the developer favorites with Default', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('togglePinnedTableViewFilter', 'big')->call('applyDefaultTableView');

        expect($component->get('tableViewFavoriteFilters'))->toBeNull();

        expect(Livewire::test(ListOrdersPhase3::class)->get('tableViewFavoriteFilters'))->toBeNull();
    });

    it('pins what a preset names', function () {
        phase3Presets(['pins' => PresetTableView::make()->defaultFavoriteFilters(['big', 'multi'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'pins');

        expect($component->get('tableViewFavoriteFilters'))->toBe(['multi', 'big'])
            ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse()
            ->and(chipNames($component->html()))->toBe(['multi', 'big']);
    });

    it('treats an empty favorite list as "none"', function () {
        phase3Presets(['none' => PresetTableView::make()->defaultFavoriteFilters([])]);

        expect(Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'none')->get('tableViewFavoriteFilters'))->toBe([]);
    });

    it('resets the pins for a preset that does not name any', function () {
        phase3Presets(['plain' => PresetTableView::make()->defaultSearch('x')]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('togglePinnedTableViewFilter', 'big')->call('applyTablePresetView', 'plain');

        expect($component->get('tableViewFavoriteFilters'))->toBeNull();
    });

    it('keeps the pins when the preset preserves filters', function () {
        phase3Presets(['keep' => PresetTableView::make()->defaultFavoriteFilters(['big'])->preserveFilters()]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('togglePinnedTableViewFilter', 'multi')->call('applyTablePresetView', 'keep');

        expect($component->get('tableViewFavoriteFilters'))->toBe(['status', 'multi']);
    });

    it('applies the favorites of a developer default preset on the first load', function () {
        phase3Presets(['start' => PresetTableView::make()->default()->defaultFavoriteFilters(['big'])]);

        expect(favoritesOf(Livewire::test(ListOrdersPhase3Presets::class)))->toBe(['big']);
    });

    it('lets a preset set a favorite and a filter value together', function () {
        phase3Presets(['p' => PresetTableView::make()->defaultFilters(['big' => ['isActive' => true]])->defaultFavoriteFilters(['big'])]);

        $component = Livewire::test(ListOrdersPhase3Presets::class)->call('applyTablePresetView', 'p');

        expect($component->get('tableFilters.big.isActive'))->toBeTrue()
            ->and(chipNames($component->html()))->toBe(['big']);
    });
});
