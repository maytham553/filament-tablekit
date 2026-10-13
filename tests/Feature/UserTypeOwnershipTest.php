<?php

use Livewire\Livewire;
use TableKit\Models\TablePreference;
use TableKit\Models\TableView;
use TableKit\Presets\PresetTableView;
use TableKit\Support\CurrentContext;
use TableKit\Tests\Fixtures\AdminUser;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;
use TableKit\Tests\Fixtures\User;

beforeEach(function () {
    config(['tablekit.managed_default_views' => true]);

    $this->seedOrders();

    presets(fn () => [
        'a' => PresetTableView::make()->label('Alpha')->favorite()->defaultSearch('ORD-01'),
        'b' => PresetTableView::make()->label('Beta')->favorite()->defaultSearch('ORD-02'),
        'c' => PresetTableView::make()->label('Gamma')->defaultSearch('ORD-03'),
    ]);

    $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.test', 'password' => 'x']);
    $this->admin = AdminUser::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x']);

    expect($this->alice->getKey())->toBe($this->admin->getKey());

    $this->theirs = storeView($this->alice->id, ['search' => 'secret'], ['name' => 'Alice only', 'sort' => 1, 'resource' => ListOrdersWithPresets::class]);
    $this->second = storeView($this->alice->id, ['search' => 'two'], ['name' => 'Alice two', 'sort' => 2, 'resource' => ListOrdersWithPresets::class]);

    $this->actingAs($this->alice);

    Livewire::test(ListOrdersWithPresets::class)
        ->call('openTableViewsManager')
        ->call('toggleTableViewFavorite', 'preset', 'a')
        ->call('toggleTableViewDefault', 'user', (string) $this->theirs->id);

    $this->actingAs($this->admin);
});

function adminManager()
{
    return Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager');
}

function aliceRows(): array
{
    return TablePreference::query()->where('user_type', User::class)->orderBy('id')->get(['view_type', 'view_key', 'is_favorite', 'is_default', 'sort'])->toArray();
}

it('reads the morph class of the signed in user', function () {
    expect(CurrentContext::userType())->toBe(AdminUser::class);

    $this->actingAs($this->alice);

    expect(CurrentContext::userType())->toBe(User::class);
});

it('stores the user type on every view and preference row', function () {
    quickSave(adminManager(), 'Admin view');

    adminManager()->call('toggleTableViewFavorite', 'preset', 'b');

    expect(TableView::where('name', 'Admin view')->value('user_type'))->toBe(AdminUser::class)
        ->and(TablePreference::query()->where('user_type', AdminUser::class)->count())->toBe(1)
        ->and(TablePreference::query()->whereNull('user_type')->count())->toBe(0);
});

it('does not show or apply the views of a user of another type with the same id', function () {
    adminManager()
        ->assertDontSee('Alice only')
        ->call('applyTableView', $this->theirs->id)
        ->assertSet('activeTableViewId', null)
        ->assertSet('tableSearch', '');
});

it('does not edit a view of a user of another type with the same id', function () {
    adminManager()
        ->call('mountAction', 'editTableView', ['viewId' => $this->theirs->id])
        ->assertSet('mountedActions', []);

    adminManager()
        ->call('mountAction', 'editTableView', ['viewId' => $this->second->id])
        ->assertSet('mountedActions', []);

    adminManager()->callAction('editTableView', ['name' => 'Hijacked', 'color' => 'primary'], ['viewId' => $this->theirs->id]);

    expect($this->theirs->fresh()->name)->toBe('Alice only');
});

it('does not delete a view of a user of another type with the same id', function () {
    adminManager()->callAction('deleteTableView', arguments: ['viewId' => $this->theirs->id]);

    expect(TableView::count())->toBe(2)
        ->and(TablePreference::query()->where('user_type', User::class)->where('view_key', (string) $this->theirs->id)->exists())->toBeTrue();
});

it('does not favorite, reorder or default a view of a user of another type with the same id', function () {
    $before = aliceRows();

    adminManager()
        ->call('toggleTableViewFavorite', 'user', (string) $this->theirs->id)
        ->call('toggleTableViewDefault', 'user', (string) $this->second->id)
        ->call('moveTableView', 'user', (string) $this->theirs->id, 'down')
        ->call('reorderTableViews', 'user', true, [(string) $this->second->id, (string) $this->theirs->id]);

    expect($this->theirs->fresh())->is_favorite->toBeTrue()->sort->toBe(1)
        ->and($this->second->fresh())->is_favorite->toBeTrue()->sort->toBe(2)
        ->and(aliceRows())->toBe($before)
        ->and(TablePreference::query()->where('user_type', AdminUser::class)->count())->toBe(0);
});

it('keeps preferences and defaults of the two user types apart', function () {
    $before = aliceRows();

    $component = adminManager()
        ->call('toggleTableViewFavorite', 'preset', 'b')
        ->call('toggleTableViewDefault', 'preset', 'c');

    $items = callProtected($component, 'getTableViewsCatalog')->presets->keyBy('key');

    expect($items['a']->isFavorite)->toBeTrue()
        ->and($items['b']->isFavorite)->toBeFalse()
        ->and($items['c']->isDefault)->toBeTrue()
        ->and(aliceRows())->toBe($before);

    $this->actingAs($this->alice);

    $items = callProtected(adminManager(), 'getTableViewsCatalog')->presets->keyBy('key');

    expect($items['a']->isFavorite)->toBeFalse()
        ->and($items['b']->isFavorite)->toBeTrue()
        ->and($items['c']->isDefault)->toBeFalse();
});

it('allows the same view name for both user types', function () {
    quickSave(adminManager(), 'Alice only');

    expect(TableView::where('name', 'Alice only')->count())->toBe(2);
});

it('numbers the sort of each user type on its own', function () {
    quickSave(adminManager(), 'First admin view');

    expect(TableView::where('name', 'First admin view')->value('sort'))->toBe(1);
});

it('lets a user type without a stored type see nothing of a typed user', function () {
    $legacy = storeView($this->admin->id, [], ['name' => 'No type', 'user_type' => null, 'resource' => ListOrdersWithPresets::class]);

    adminManager()->assertDontSee('No type');

    expect($legacy->fresh()->user_type)->toBeNull();
});
