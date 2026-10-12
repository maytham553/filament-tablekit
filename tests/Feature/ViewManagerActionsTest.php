<?php

use Elite\TableViews\Models\TablePreference;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Presets\PresetTableView;
use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;
use Elite\TableViews\Tests\Fixtures\Team;
use Elite\TableViews\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    presets(fn () => [
        'a' => PresetTableView::make()->label('Alpha')->favorite()->defaultSearch('ORD-01'),
        'b' => PresetTableView::make()->label('Beta')->favorite()->defaultSearch('ORD-02'),
        'c' => PresetTableView::make()->label('Gamma')->defaultSearch('ORD-03'),
        'd' => PresetTableView::make()->label('Delta')->defaultSearch('ORD-04'),
    ]);

    $this->v1 = storeView($this->user->id, ['search' => 'one'], ['name' => 'Mine one', 'sort' => 1, 'resource' => ListOrdersWithPresets::class]);
    $this->v2 = storeView($this->user->id, ['search' => 'two'], ['name' => 'Mine two', 'sort' => 2, 'resource' => ListOrdersWithPresets::class]);
    $this->v3 = storeView($this->user->id, ['search' => 'three'], ['name' => 'Mine three', 'sort' => 3, 'is_favorite' => false, 'resource' => ListOrdersWithPresets::class]);
    $this->v4 = storeView($this->user->id, ['search' => 'four'], ['name' => 'Mine four', 'sort' => 4, 'is_favorite' => false, 'resource' => ListOrdersWithPresets::class]);

    $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'x']);
});

function manager(): Testable
{
    return Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager');
}

function presetOrder(Testable $component): array
{
    return callProtected($component, 'getTableViewsCatalog')->presets->pluck('key')->all();
}

function userViewOrder(Testable $component): array
{
    return callProtected($component, 'getTableViewsCatalog')->userViews->pluck('key')->map(fn ($key) => (int) $key)->all();
}

function preferenceRow(string $type, string|int $key, ?int $userId = null): ?TablePreference
{
    return TablePreference::where('user_id', (string) ($userId ?? test()->user->id))
        ->where('resource', ListOrdersWithPresets::class)
        ->where('view_type', $type)
        ->where('view_key', (string) $key)
        ->first();
}

describe('apply', function () {
    it('applies a preset and a user view from the manager calls', function () {
        manager()
            ->call('applyTablePresetView', 'c')
            ->assertSet('activeTablePresetKey', 'c')
            ->assertSet('tableSearch', 'ORD-03')
            ->call('applyTableView', $this->v2->id)
            ->assertSet('activeTablePresetKey', null)
            ->assertSet('activeTableViewId', $this->v2->id)
            ->assertSet('tableSearch', 'two');
    });

    it('closes the slide over after applying', function () {
        TableViewsPlugin::get()->viewManager(slideOver: true);

        $component = Livewire::test(ListOrdersWithPresets::class)
            ->mountAction('manageTableViews')
            ->assertSet('mountedActions', ['manageTableViews'])
            ->call('applyTablePresetView', 'a')
            ->assertSet('mountedActions', [])
            ->assertSet('activeTablePresetKey', 'a');

        $component->mountAction('manageTableViews')->call('applyTableView', $this->v1->id)->assertSet('mountedActions', []);
    });
});

describe('favorites', function () {
    it('toggles a preset favorite on and off and stores null when it matches the developer default', function () {
        $component = manager();

        $component->call('toggleTableViewFavorite', 'preset', 'a');
        expect(preferenceRow('preset', 'a')->is_favorite)->toBeFalse();
        expect($component->html())->not->toContain('wire:key="table-preset-a"');

        $component->call('toggleTableViewFavorite', 'preset', 'a');
        expect(preferenceRow('preset', 'a')->is_favorite)->toBeNull();

        $component->call('toggleTableViewFavorite', 'preset', 'c');
        expect(preferenceRow('preset', 'c')->is_favorite)->toBeTrue();
    });

    it('updates the favorites bar right after the change', function () {
        $component = Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager');

        expect($component->html())->toContain('table-preset-a');

        $component->call('toggleTableViewFavorite', 'preset', 'a');

        expect($component->html())->not->toContain('wire:key="table-preset-a"');

        $component->call('toggleTableViewFavorite', 'preset', 'c');

        expect($component->html())->toContain('wire:key="table-preset-c"');
    });

    it('toggles a user view favorite on the views table', function () {
        manager()->call('toggleTableViewFavorite', 'user', (string) $this->v1->id);

        expect($this->v1->fresh()->is_favorite)->toBeFalse();

        manager()->call('toggleTableViewFavorite', 'user', (string) $this->v3->id);

        expect($this->v3->fresh()->is_favorite)->toBeTrue();
    });

    it('accepts an integer user view key', function () {
        manager()->call('toggleTableViewFavorite', 'user', $this->v1->id);

        expect($this->v1->fresh()->is_favorite)->toBeFalse();
    });

    it('moves a toggled user view to the other section but keeps the type order', function () {
        $component = manager()->call('toggleTableViewFavorite', 'user', (string) $this->v1->id);

        $catalog = callProtected($component, 'getTableViewsCatalog');

        expect($catalog->myViews()->pluck('key')->map(fn ($k) => (int) $k)->all())->toBe([$this->v1->id, $this->v3->id, $this->v4->id])
            ->and($catalog->favoriteUserViews()->pluck('key')->map(fn ($k) => (int) $k)->all())->toBe([$this->v2->id]);
    });

    it('refuses to change preset favorites when presets are not manageable', function () {
        config(['table-views.preset_views.manageable' => false]);

        manager()->call('toggleTableViewFavorite', 'preset', 'a')->call('toggleTableViewFavorite', 'preset', 'c');

        expect(TablePreference::count())->toBe(0);
    });
});

describe('reorder', function () {
    it('moves a favorite preset up and down and stores a full order', function () {
        $component = manager()->call('moveTableView', 'preset', 'b', 'up');

        expect(presetOrder($component))->toBe(['b', 'a', 'c', 'd'])
            ->and(preferenceRow('preset', 'b')->sort)->toBe(0)
            ->and(preferenceRow('preset', 'a')->sort)->toBe(1)
            ->and(preferenceRow('preset', 'c')->sort)->toBe(2)
            ->and(preferenceRow('preset', 'd')->sort)->toBe(3);

        $component->call('moveTableView', 'preset', 'b', 'down');

        expect(presetOrder($component))->toBe(['a', 'b', 'c', 'd']);
    });

    it('does nothing at the edges of a section', function () {
        $component = manager()
            ->call('moveTableView', 'preset', 'a', 'up')
            ->call('moveTableView', 'preset', 'b', 'down')
            ->call('moveTableView', 'preset', 'c', 'up')
            ->call('moveTableView', 'preset', 'd', 'down')
            ->call('moveTableView', 'user', (string) $this->v1->id, 'up')
            ->call('moveTableView', 'user', (string) $this->v2->id, 'down')
            ->call('moveTableView', 'user', (string) $this->v3->id, 'up')
            ->call('moveTableView', 'user', (string) $this->v4->id, 'down');

        expect(TablePreference::count())->toBe(0)
            ->and(presetOrder($component))->toBe(['a', 'b', 'c', 'd'])
            ->and(userViewOrder($component))->toBe([$this->v1->id, $this->v2->id, $this->v3->id, $this->v4->id]);
    });

    it('moves inside the section only: a favorite never swaps with a non favorite', function () {
        $component = manager()->call('moveTableView', 'preset', 'd', 'up');

        expect(presetOrder($component))->toBe(['a', 'b', 'd', 'c']);

        $component->call('moveTableView', 'preset', 'b', 'down');

        expect(presetOrder($component))->toBe(['a', 'b', 'd', 'c']);
    });

    it('moves user views by the views table sort column', function () {
        $component = manager()->call('moveTableView', 'user', (string) $this->v1->id, 'down');

        expect(userViewOrder($component))->toBe([$this->v2->id, $this->v1->id, $this->v3->id, $this->v4->id])
            ->and($this->v2->fresh()->sort)->toBe(1)
            ->and($this->v1->fresh()->sort)->toBe(2);

        $component->call('moveTableView', 'user', (string) $this->v4->id, 'up');

        expect(userViewOrder($component))->toBe([$this->v2->id, $this->v1->id, $this->v4->id, $this->v3->id]);
    });

    it('shows the new order in the bar', function () {
        $component = manager()->call('moveTableView', 'preset', 'b', 'up');

        $component->assertSeeInOrder(['Beta', 'Alpha', 'Mine one']);

        manager()->call('moveTableView', 'user', (string) $this->v1->id, 'down')->assertSeeInOrder(['Mine two', 'Mine one']);
    });

    it('keeps a new preset after or before the ranked ones', function () {
        manager()->call('moveTableView', 'preset', 'b', 'up');

        presets(fn () => [
            'a' => PresetTableView::make()->label('Alpha')->favorite(),
            'b' => PresetTableView::make()->label('Beta')->favorite(),
            'c' => PresetTableView::make()->label('Gamma'),
            'd' => PresetTableView::make()->label('Delta'),
            'new' => PresetTableView::make()->label('Brand new')->favorite(),
        ]);

        expect(presetOrder(manager()))->toBe(['b', 'a', 'c', 'd', 'new']);

        config(['table-views.preset_views.new_sort_position' => 'before']);

        expect(presetOrder(manager()))->toBe(['new', 'b', 'a', 'c', 'd']);
    });

    it('refuses to reorder presets when they are not manageable but still reorders user views', function () {
        config(['table-views.preset_views.manageable' => false]);

        $component = manager()
            ->call('moveTableView', 'preset', 'b', 'up')
            ->call('moveTableView', 'user', (string) $this->v1->id, 'down');

        expect(presetOrder($component))->toBe(['a', 'b', 'c', 'd'])
            ->and(TablePreference::count())->toBe(0)
            ->and(userViewOrder($component)[0])->toBe($this->v2->id);
    });

    it('ignores stored preset order and favorites when presets are not manageable', function () {
        storePreference($this->user->id, 'preset', 'b', ['sort' => 0, 'is_favorite' => false]);
        storePreference($this->user->id, 'preset', 'a', ['sort' => 1]);

        config(['table-views.preset_views.manageable' => false]);

        $catalog = callProtected(manager(), 'getTableViewsCatalog');

        expect($catalog->presets->pluck('key')->all())->toBe(['a', 'b', 'c', 'd'])
            ->and($catalog->favoritePresets()->pluck('key')->all())->toBe(['a', 'b']);
    });
});

describe('drag reorder', function () {
    it('stores a dragged order for every section and leaves the other sections alone', function () {
        $component = manager()
            ->call('reorderTableViews', 'preset', true, ['b', 'a'])
            ->call('reorderTableViews', 'preset', false, ['d', 'c'])
            ->call('reorderTableViews', 'user', true, [(string) $this->v2->id, (string) $this->v1->id])
            ->call('reorderTableViews', 'user', false, [$this->v4->id, $this->v3->id]);

        expect(presetOrder($component))->toBe(['b', 'a', 'd', 'c'])
            ->and(userViewOrder($component))->toBe([$this->v2->id, $this->v1->id, $this->v4->id, $this->v3->id])
            ->and(preferenceRow('preset', 'b')->sort)->toBe(0)
            ->and($this->v2->fresh()->sort)->toBe(1)
            ->and($this->v4->fresh()->sort)->toBe(3);
    });

    it('moves only the dragged section when favorites and non favorites are interleaved', function () {
        $this->v1->update(['sort' => 1, 'is_favorite' => true]);
        $this->v2->update(['sort' => 2, 'is_favorite' => false]);
        $this->v3->update(['sort' => 3, 'is_favorite' => true]);
        $this->v4->update(['sort' => 4, 'is_favorite' => false]);

        $component = manager()->call('reorderTableViews', 'user', true, [(string) $this->v3->id, (string) $this->v1->id]);

        expect(userViewOrder($component))->toBe([$this->v3->id, $this->v2->id, $this->v1->id, $this->v4->id]);
    });

    it('shows a dragged order in the rendered list', function () {
        manager()->call('reorderTableViews', 'preset', true, ['b', 'a'])->assertSeeInOrder(['Beta', 'Alpha', 'Mine one']);
    });

    it('writes nothing for an unchanged order', function () {
        manager()->call('reorderTableViews', 'preset', true, ['a', 'b'])->call('reorderTableViews', 'user', false, [$this->v3->id, $this->v4->id]);

        expect(TablePreference::count())->toBe(0);
    });

    it('ignores a tampered order and never errors', function (string $type, bool $favorite, mixed $keys) {
        $component = manager()->call('reorderTableViews', $type, $favorite, $keys)->assertSuccessful();

        expect(TablePreference::count())->toBe(0)
            ->and(presetOrder($component))->toBe(['a', 'b', 'c', 'd'])
            ->and(userViewOrder($component))->toBe([$this->v1->id, $this->v2->id, $this->v3->id, $this->v4->id])
            ->and($this->v1->fresh()->sort)->toBe(1)
            ->and($this->v4->fresh()->sort)->toBe(4);
    })->with([
        'missing key' => ['preset', true, ['a']],
        'empty' => ['preset', true, []],
        'extra key from another section' => ['preset', true, ['b', 'a', 'c']],
        'swapped with another section' => ['preset', true, ['a', 'c']],
        'whole other section' => ['preset', true, ['c', 'd']],
        'duplicate' => ['preset', true, ['a', 'a']],
        'unknown key' => ['preset', true, ['a', 'zzz']],
        'oversized' => ['preset', true, array_map(fn (int $i): string => "k{$i}", range(1, 5000))],
        'huge key' => ['preset', true, ['a', str_repeat('k', 100000)]],
        'nested array' => ['preset', true, [['a'], 'b']],
        'null entry' => ['preset', true, [null, 'b']],
        'bool entry' => ['preset', true, [true, 'b']],
        'float entry' => ['preset', true, [1.5, 'b']],
        'string instead of list' => ['preset', true, 'b,a'],
        'null instead of list' => ['preset', true, null],
        'keyed object' => ['preset', true, ['x' => 'b', 'y' => 'a']],
        'wrong favorite flag' => ['preset', false, ['b', 'a']],
        'unknown type' => ['group', true, ['b', 'a']],
        'user view unknown' => ['user', false, ['999999', '888888']],
        'user view letters' => ['user', false, ['abc', 'def']],
    ]);

    it('ignores a user view order that belongs to another section or holds foreign ids', function () {
        $favorites = [(string) $this->v1->id, (string) $this->v2->id];
        $others = [(string) $this->v3->id, (string) $this->v4->id];

        $component = manager()
            ->call('reorderTableViews', 'user', true, $others)
            ->call('reorderTableViews', 'user', false, $favorites)
            ->call('reorderTableViews', 'user', true, [...$favorites, ...$others])
            ->call('reorderTableViews', 'user', true, [$favorites[1], $others[0]])
            ->call('reorderTableViews', 'user', true, [[$favorites[1]], $favorites[0]])
            ->assertSuccessful();

        expect(userViewOrder($component))->toBe([$this->v1->id, $this->v2->id, $this->v3->id, $this->v4->id])
            ->and($this->v1->fresh()->sort)->toBe(1)
            ->and($this->v2->fresh()->sort)->toBe(2);
    });

    it('ignores a non scalar type or favorite flag', function (mixed $type, mixed $favorite) {
        $component = manager()->call('reorderTableViews', $type, $favorite, ['b', 'a'])->assertSuccessful();

        expect(TablePreference::count())->toBe(0)->and(presetOrder($component))->toBe(['a', 'b', 'c', 'd']);
    })->with([
        'array type' => [['preset'], true],
        'null type' => [null, true],
        'int favorite' => ['preset', 1],
        'string favorite' => ['preset', 'true'],
        'null favorite' => ['preset', null],
    ]);

    it('never reorders another users views', function () {
        $theirs = storeView($this->other->id, [], ['name' => 'Theirs', 'sort' => 1, 'resource' => ListOrdersWithPresets::class]);
        $theirs2 = storeView($this->other->id, [], ['name' => 'Theirs two', 'sort' => 2, 'resource' => ListOrdersWithPresets::class]);

        manager()
            ->call('reorderTableViews', 'user', true, [(string) $theirs2->id, (string) $theirs->id])
            ->call('reorderTableViews', 'user', true, [(string) $theirs->id, (string) $this->v2->id])
            ->call('reorderTableViews', 'user', true, [(string) $this->v2->id, (string) $this->v1->id, (string) $theirs->id]);

        expect($theirs->fresh()->sort)->toBe(1)
            ->and($theirs2->fresh()->sort)->toBe(2)
            ->and($this->v1->fresh()->sort)->toBe(1)
            ->and($this->v2->fresh()->sort)->toBe(2);
    });

    it('refuses to reorder presets when they are not manageable but still reorders user views', function () {
        config(['table-views.preset_views.manageable' => false]);

        $component = manager()
            ->call('reorderTableViews', 'preset', true, ['b', 'a'])
            ->call('reorderTableViews', 'user', true, [(string) $this->v2->id, (string) $this->v1->id]);

        expect(presetOrder($component))->toBe(['a', 'b', 'c', 'd'])
            ->and(TablePreference::count())->toBe(0)
            ->and(userViewOrder($component)[0])->toBe($this->v2->id);
    });

    it('does nothing when the manager is disabled', function () {
        TableViewsPlugin::get()->viewManager(false);

        Livewire::test(ListOrdersWithPresets::class)->call('reorderTableViews', 'preset', true, ['b', 'a'])->assertSuccessful();

        expect(TablePreference::count())->toBe(0);
    });

    it('serves the keyboard move through the same order call', function () {
        $component = manager()->call('reorderTableViews', 'user', false, [$this->v4->id, $this->v3->id]);

        expect(userViewOrder($component))->toBe([$this->v1->id, $this->v2->id, $this->v4->id, $this->v3->id]);

        $component->call('reorderTableViews', 'user', false, [(string) $this->v3->id, (string) $this->v4->id]);

        expect(userViewOrder($component))->toBe([$this->v1->id, $this->v2->id, $this->v3->id, $this->v4->id]);
    });
});

describe('edit', function () {
    it('updates name, color and icon of an own view', function () {
        manager()
            ->callAction('editTableView', ['name' => '  Renamed  ', 'color' => 'danger', 'icon' => 'star'], ['viewId' => $this->v1->id])
            ->assertHasNoActionErrors();

        $view = $this->v1->fresh();

        expect([$view->name, $view->color, $view->icon])->toBe(['Renamed', 'danger', 'star']);
    });

    it('prefills the form with the current values', function () {
        $this->v1->update(['color' => 'success', 'icon' => 'bolt']);

        manager()
            ->mountAction('editTableView', ['viewId' => $this->v1->id])
            ->assertActionDataSet(['name' => 'Mine one', 'color' => 'success', 'icon' => 'bolt']);
    });

    it('validates like quick save', function (array $data, string $error) {
        manager()
            ->callAction('editTableView', ['color' => 'primary', ...$data], ['viewId' => $this->v1->id])
            ->assertHasActionErrors(['name' => [$error]]);

        expect($this->v1->fresh()->name)->toBe('Mine one');
    })->with([
        'empty' => [['name' => ''], 'required'],
        'too long' => [['name' => str_repeat('x', 101)], 'max'],
    ]);

    it('rejects a name another view of the user already has, ignoring case', function () {
        manager()
            ->callAction('editTableView', ['name' => 'MINE TWO', 'color' => 'primary'], ['viewId' => $this->v1->id])
            ->assertHasActionErrors(['name']);

        expect($this->v1->fresh()->name)->toBe('Mine one');
    });

    it('lets a view keep or recase its own name', function () {
        manager()
            ->callAction('editTableView', ['name' => 'MINE ONE', 'color' => 'primary'], ['viewId' => $this->v1->id])
            ->assertHasNoActionErrors();

        expect($this->v1->fresh()->name)->toBe('MINE ONE');
    });

    it('allows a name another user has', function () {
        storeView($this->other->id, [], ['name' => 'Shared name', 'resource' => ListOrdersWithPresets::class]);

        manager()
            ->callAction('editTableView', ['name' => 'Shared name', 'color' => 'primary'], ['viewId' => $this->v1->id])
            ->assertHasNoActionErrors();
    });

    it('rejects an unknown color or icon', function () {
        manager()
            ->callAction('editTableView', ['name' => 'Fine', 'color' => 'neon', 'icon' => 'nope'], ['viewId' => $this->v1->id])
            ->assertHasActionErrors(['color', 'icon']);

        expect($this->v1->fresh()->name)->toBe('Mine one');
    });

    it('does not open for a view that is not the users own', function (callable $make) {
        $view = $make($this);

        manager()
            ->call('mountAction', 'editTableView', ['viewId' => $view->id])
            ->assertSet('mountedActions', []);

        expect($view->fresh()->name)->toBe($view->name);
    })->with([
        'other user' => [fn ($t) => storeView($t->other->id, [], ['name' => 'Theirs', 'resource' => ListOrdersWithPresets::class])],
        'other table' => [fn ($t) => storeView($t->user->id, [], ['name' => 'Elsewhere', 'resource' => ListOrders::class])],
    ]);

    it('does not save when the argument is swapped after the form opened', function () {
        $theirs = storeView($this->other->id, [], ['name' => 'Theirs', 'resource' => ListOrdersWithPresets::class]);

        $component = manager()->mountAction('editTableView', ['viewId' => $this->v1->id]);

        $component->set('mountedActionsArguments.0.viewId', $theirs->id)
            ->setActionData(['name' => 'Hijacked', 'color' => 'primary'])
            ->callMountedAction();

        expect($theirs->fresh()->name)->toBe('Theirs')
            ->and($this->v1->fresh()->name)->toBe('Mine one');
    });

    it('ignores hostile view ids', function (mixed $viewId) {
        manager()
            ->call('mountAction', 'editTableView', ['viewId' => $viewId])
            ->assertSuccessful()
            ->assertSet('mountedActions', []);
    })->with([
        'letters' => 'abc',
        'negative' => -1,
        'zero' => 0,
        'overflow' => '99999999999999999999',
        'array' => [[1]],
        'bool' => true,
        'null' => null,
        'float' => 1.5,
        'ulid' => '01J00000000000000000000000',
    ]);
});

describe('delete', function () {
    it('deletes an own view after confirmation', function () {
        manager()
            ->callAction('deleteTableView', arguments: ['viewId' => $this->v2->id])
            ->assertNotified(__('table-views::manager.delete.deleted'));

        expect(TableView::find($this->v2->id))->toBeNull()
            ->and(TableView::count())->toBe(3);
    });

    it('requires confirmation', function () {
        $component = manager()->mountAction('deleteTableView', ['viewId' => $this->v2->id]);

        expect($component->instance()->getMountedAction()->getModalHeading())->toBe(__('table-views::manager.delete.heading'))
            ->and($component->instance()->getMountedAction()->shouldOpenModal())->toBeTrue();
    });

    it('never deletes a view of another user, table or tenant', function () {
        $theirs = storeView($this->other->id, [], ['name' => 'Theirs', 'resource' => ListOrdersWithPresets::class]);
        $elsewhere = storeView($this->user->id, [], ['name' => 'Elsewhere', 'resource' => ListOrders::class]);

        $component = manager();

        foreach ([$theirs, $elsewhere] as $view) {
            $component->call('mountAction', 'deleteTableView', ['viewId' => $view->id])->assertSet('mountedActions', []);
        }

        $component->mountAction('deleteTableView', ['viewId' => $this->v1->id])
            ->set('mountedActionsArguments.0.viewId', $theirs->id)
            ->callMountedAction();

        expect(TableView::count())->toBe(6);
    });

    it('clears the active marker but leaves the table state', function () {
        $component = manager()->call('applyTableView', $this->v1->id)->assertSet('tableSearch', 'one');

        $component->callAction('deleteTableView', arguments: ['viewId' => $this->v1->id]);

        $component->assertSet('activeTableViewId', null)->assertSet('activeTableViewHash', null)->assertSet('tableSearch', 'one');
    });

    it('keeps the active marker when another view is deleted', function () {
        $component = manager()->call('applyTableView', $this->v1->id);

        $component->callAction('deleteTableView', arguments: ['viewId' => $this->v2->id]);

        $component->assertSet('activeTableViewId', $this->v1->id);
    });

    it('removes the preference rows of a deleted view', function () {
        config(['table-views.managed_default_views' => true]);

        $component = manager()->call('toggleTableViewDefault', 'user', (string) $this->v2->id);

        expect(preferenceRow('user', $this->v2->id)->is_default)->toBeTrue();

        $component->callAction('deleteTableView', arguments: ['viewId' => $this->v2->id]);

        expect(preferenceRow('user', $this->v2->id))->toBeNull();
    });

    it('drops the view from the bar', function () {
        $component = manager();

        $component->assertSee('Mine two')->callAction('deleteTableView', arguments: ['viewId' => $this->v2->id]);

        $component->assertDontSee('Mine two');
    });
});

describe('default', function () {
    beforeEach(fn () => config(['table-views.managed_default_views' => true]));

    it('sets and removes a default for a preset and a user view', function () {
        $component = manager()->call('toggleTableViewDefault', 'preset', 'c');

        expect(preferenceRow('preset', 'c')->is_default)->toBeTrue();

        $component->call('toggleTableViewDefault', 'preset', 'c');

        expect(preferenceRow('preset', 'c')->is_default)->toBeFalse();

        $component->call('toggleTableViewDefault', 'user', (string) $this->v3->id);

        expect(preferenceRow('user', $this->v3->id)->is_default)->toBeTrue();
    });

    it('keeps one default per user, tenant and table', function () {
        $component = manager()->call('toggleTableViewDefault', 'preset', 'c');
        $component->call('toggleTableViewDefault', 'user', (string) $this->v3->id);

        expect(TablePreference::where('is_default', true)->count())->toBe(1)
            ->and(preferenceRow('preset', 'c')->is_default)->toBeFalse()
            ->and(preferenceRow('user', $this->v3->id)->is_default)->toBeTrue();
    });

    it('does not touch the default of another user, table or tenant', function () {
        $theirs = TablePreference::create(['user_id' => $this->other->id, 'resource' => ListOrdersWithPresets::class, 'view_type' => 'preset', 'view_key' => 'c', 'is_default' => true]);
        $elsewhere = TablePreference::create(['user_id' => $this->user->id, 'resource' => ListOrders::class, 'view_type' => 'preset', 'view_key' => 'c', 'is_default' => true]);

        manager()->call('toggleTableViewDefault', 'preset', 'a');

        expect($theirs->fresh()->is_default)->toBeTrue()
            ->and($elsewhere->fresh()->is_default)->toBeTrue();
    });

    it('is ignored while managed defaults are off', function () {
        config(['table-views.managed_default_views' => false]);

        manager()->call('toggleTableViewDefault', 'preset', 'c');

        expect(TablePreference::count())->toBe(0);
    });

    it('cannot target a hidden preset or the default key', function () {
        presets(fn () => [
            'secret' => PresetTableView::make()->label('Secret')->visible(false),
            'default' => PresetTableView::make()->label('Everything'),
            'ok' => PresetTableView::make()->label('Fine'),
        ]);

        manager()
            ->call('toggleTableViewDefault', 'preset', 'secret')
            ->call('toggleTableViewDefault', 'preset', 'default');

        expect(TablePreference::count())->toBe(0);
    });

    it('shows the default marker in the open manager', function () {
        manager()
            ->call('toggleTableViewDefault', 'preset', 'c')
            ->assertSee(__('table-views::manager.actions.remove_default'));
    });
});

describe('scoping', function () {
    it('keeps preference rows per tenant', function () {
        config(['table-views.managed_default_views' => true]);

        $x = Team::create(['name' => 'X']);
        $y = Team::create(['name' => 'Y']);

        Filament::setCurrentPanel(Filament::getPanel('team'));
        Filament::bootCurrentPanel();
        Filament::setTenant($x);

        manager()->call('toggleTableViewFavorite', 'preset', 'a')->call('toggleTableViewDefault', 'preset', 'c');

        $row = TablePreference::where('view_key', 'a')->first();

        expect($row->tenant_type)->toBe($x->getMorphClass())
            ->and($row->tenant_id)->toBe((string) $x->id);

        Filament::setTenant($y);

        $catalog = callProtected(manager(), 'getTableViewsCatalog');

        expect($catalog->find('preset', 'a')->isFavorite)->toBeTrue()
            ->and($catalog->defaultItem())->toBeNull();
    });
});

describe('hostile input', function () {
    it('ignores bad types, keys and directions on every manager call', function (mixed $type, mixed $key, mixed $direction) {
        config(['table-views.managed_default_views' => true]);

        $component = manager()
            ->call('toggleTableViewFavorite', $type, $key)
            ->call('toggleTableViewDefault', $type, $key)
            ->call('moveTableView', $type, $key, $direction)
            ->assertSuccessful();

        expect(TablePreference::count())->toBe(0)
            ->and($this->v1->fresh()->is_favorite)->toBeTrue()
            ->and(presetOrder($component))->toBe(['a', 'b', 'c', 'd']);
    })->with([
        'unknown type' => ['group', 'a', 'up'],
        'array type' => [['preset'], 'a', 'up'],
        'null type' => [null, 'a', 'up'],
        'array key' => ['preset', ['a'], 'up'],
        'null key' => ['preset', null, 'up'],
        'bool key' => ['preset', true, 'up'],
        'float key' => ['user', 1.5, 'up'],
        'unknown preset' => ['preset', 'zzz', 'up'],
        'huge key' => ['preset', str_repeat('k', 300), 'up'],
        'user letters' => ['user', 'abc', 'up'],
        'user overflow' => ['user', '99999999999999999999', 'up'],
        'user negative' => ['user', '-1', 'up'],
        'user ulid' => ['user', '01J00000000000000000000000', 'up'],
        'sql' => ['preset', "a' OR '1'='1", 'up'],
    ]);

    it('ignores a bad move direction', function (mixed $direction) {
        $component = manager()->call('moveTableView', 'preset', 'b', $direction)->call('moveTableView', 'user', (string) $this->v2->id, $direction);

        expect(TablePreference::count())->toBe(0)
            ->and(presetOrder($component))->toBe(['a', 'b', 'c', 'd'])
            ->and(userViewOrder($component)[0])->toBe($this->v1->id);
    })->with(['sideways', [['up']], '', null, 1, 'UP']);

    it('does not let a user favorite, move or default another users view', function () {
        config(['table-views.managed_default_views' => true]);

        $theirs = storeView($this->other->id, [], ['name' => 'Theirs', 'sort' => 1, 'resource' => ListOrdersWithPresets::class]);
        $theirs2 = storeView($this->other->id, [], ['name' => 'Theirs two', 'sort' => 2, 'resource' => ListOrdersWithPresets::class]);

        manager()
            ->call('toggleTableViewFavorite', 'user', (string) $theirs->id)
            ->call('moveTableView', 'user', (string) $theirs->id, 'down')
            ->call('toggleTableViewDefault', 'user', (string) $theirs->id);

        expect($theirs->fresh()->is_favorite)->toBeTrue()
            ->and($theirs->fresh()->sort)->toBe(1)
            ->and($theirs2->fresh()->sort)->toBe(2)
            ->and(TablePreference::count())->toBe(0);
    });

    it('cannot call the manager actions as plain methods from the browser', function (string $method) {
        expect(fn () => manager()->call($method))->toThrow(MethodNotFoundException::class);
    })->with(['getPresetTableViews', 'getTableViewsCatalog', 'resolveTablePresets', 'applyActiveTablePresetQuery', 'findTableView', 'saveTableView']);

    it('cannot unlock the manager flag from the browser', function () {
        expect(fn () => Livewire::test(ListOrdersWithPresets::class)->set('tableViewsManagerLoaded', true))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('does nothing when the manager is disabled', function () {
        TableViewsPlugin::get()->viewManager(false);

        $component = Livewire::test(ListOrdersWithPresets::class)
            ->call('openTableViewsManager')
            ->call('toggleTableViewFavorite', 'preset', 'a')
            ->call('moveTableView', 'preset', 'b', 'up')
            ->assertSet('tableViewsManagerLoaded', false);

        expect(TablePreference::count())->toBe(0)
            ->and(presetOrder($component))->toBe(['a', 'b', 'c', 'd']);
    });
});
