<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use TableKit\Models\TablePreference;
use TableKit\Models\TableView;
use TableKit\Presets\PresetTableView;
use TableKit\Support\CurrentContext;
use TableKit\Tests\Fixtures\OtherUser;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;
use TableKit\Tests\Fixtures\User;

beforeEach(function () {
    config(['tablekit.managed_default_views' => true]);

    $this->seedOrders();

    presets(fn () => [
        'a' => PresetTableView::make()->label('Alpha')->favorite()->defaultSearch('ORD-01'),
        'b' => PresetTableView::make()->label('Beta')->favorite()->defaultSearch('ORD-02'),
    ]);

    $this->a = User::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x']);
    $this->b = OtherUser::create(['name' => 'B', 'email' => 'b@example.test', 'password' => 'x']);

    $this->actingAs($this->a);

    $component = Livewire::test(ListOrdersWithPresets::class)->filterTable('status', 'paid');
    $this->view = quickSave($component, 'A view');
    $this->second = storeView($this->a->getKey(), ['search' => 'two'], ['name' => 'A second', 'sort' => 5, 'resource' => ListOrdersWithPresets::class]);

    Livewire::test(ListOrdersWithPresets::class)
        ->call('openTableViewsManager')
        ->call('toggleTableViewFavorite', 'preset', 'b')
        ->call('toggleTableViewDefault', 'preset', 'a');

    $this->aPreferences = snapshot('tablekit_preferences');
    $this->aViews = snapshot('tablekit_views');

    $this->actingAs($this->b);
});

/**
 * @return array<int, array<string, mixed>>
 */
function snapshot(string $table): array
{
    return DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
}

function asB(): Testable
{
    return Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager');
}

it('uses two user models that share the same id', function () {
    expect($this->a->getKey())->toBe($this->b->getKey())
        ->and(CurrentContext::userType())->toBe(OtherUser::class)
        ->and(TableView::query()->first()->user_type)->toBe(User::class);
});

it('stores the user type with every view and preference', function () {
    expect(TableView::pluck('user_type')->unique()->all())->toBe([User::class])
        ->and(TablePreference::pluck('user_type')->unique()->all())->toBe([User::class]);
});

it('does not list or apply the views of the other user model', function () {
    Livewire::test(ListOrdersWithPresets::class)->assertDontSee('A view');

    asB()
        ->assertDontSee('A view')
        ->call('applyTableView', $this->view->id)
        ->assertSet('activeTableViewId', null)
        ->assertSet('tableFilters.status.value', null);

    expect(TableView::forCurrentContext(ListOrdersWithPresets::class)->count())->toBe(0);
});

it('does not restore the other user models default or favorite choices', function () {
    $component = Livewire::test(ListOrdersWithPresets::class);

    $component->assertSet('tableSearch', '');
    expect(TablePreference::forCurrentContext(ListOrdersWithPresets::class)->count())->toBe(0);
});

it('cannot edit or delete a view of the other user model', function () {
    asB()
        ->call('mountAction', 'editTableView', ['viewId' => $this->view->id])
        ->assertSet('mountedActions', []);

    asB()
        ->call('mountAction', 'deleteTableView', ['viewId' => $this->view->id])
        ->assertSet('mountedActions', []);

    expect(snapshot('tablekit_views'))->toBe($this->aViews);
});

it('cannot favorite, reorder or default a view of the other user model', function () {
    asB()
        ->call('toggleTableViewFavorite', 'user', (string) $this->view->id)
        ->call('toggleTableViewDefault', 'user', (string) $this->view->id)
        ->call('reorderTableViews', 'user', true, [(string) $this->second->id, (string) $this->view->id])
        ->call('moveTableView', 'user', (string) $this->view->id, 'down');

    expect(snapshot('tablekit_views'))->toBe($this->aViews)
        ->and(snapshot('tablekit_preferences'))->toBe($this->aPreferences);
});

it('keeps preset choices of the two user models apart', function () {
    asB()
        ->call('toggleTableViewFavorite', 'preset', 'a')
        ->call('toggleTableViewDefault', 'preset', 'b')
        ->call('reorderTableViews', 'preset', true, ['b', 'a']);

    $mine = TablePreference::forCurrentContext(ListOrdersWithPresets::class)->get();

    expect($mine->pluck('user_type')->unique()->all())->toBe([OtherUser::class])
        ->and($mine->firstWhere('view_key', 'b')->is_default)->toBeTrue()
        ->and($mine->firstWhere('view_key', 'a')->is_default)->toBeFalse();

    expect(array_slice(snapshot('tablekit_preferences'), 0, count($this->aPreferences)))->toBe($this->aPreferences);
});

it('allows the same name and counts sort separately per user model', function () {
    $view = quickSave(Livewire::test(ListOrdersWithPresets::class), 'A view');

    expect($view->user_type)->toBe(OtherUser::class)
        ->and($view->sort)->toBe(1)
        ->and(TableView::where('name', 'A view')->count())->toBe(2);
});

it('scopes ownedBy by the user type as well as the id', function () {
    expect(TableView::query()->ownedBy(User::class, $this->a->getKey())->count())->toBe(2)
        ->and(TableView::query()->ownedBy(OtherUser::class, $this->b->getKey())->count())->toBe(0)
        ->and(TablePreference::query()->ownedBy(OtherUser::class, $this->b->getKey())->count())->toBe(0)
        ->and(TablePreference::query()->ownedBy(null, $this->a->getKey())->count())->toBe(0);
});

it('refuses the unique index for the same user model, and allows it across user models', function () {
    $row = fn (string $type) => TablePreference::create([
        'user_type' => $type, 'user_id' => 1, 'resource' => 'res', 'view_type' => 'preset', 'view_key' => 'x',
    ]);

    $row(User::class);
    $row(OtherUser::class);

    expect(fn () => $row(User::class))->toThrow(UniqueConstraintViolationException::class);
});
