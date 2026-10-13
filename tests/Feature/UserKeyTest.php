<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\UlidUser;
use TableKit\Tests\Fixtures\User;

function ulidUser(): UlidUser
{
    return UlidUser::create(['name' => 'Ulid', 'email' => 'u'.uniqid().'@example.test', 'password' => 'x']);
}

beforeEach(function () {
    $this->seedOrders();
});

it('stores the user key in a string column', function () {
    expect(Schema::getColumnType('tablekit_views', 'user_id'))->toBe('varchar');
});

it('saves, lists and applies views for a ULID user', function () {
    $user = ulidUser();
    $this->actingAs($user);

    expect($user->getKey())->toBeString()->toHaveLength(26);

    $source = Livewire::test(ListOrders::class)->filterTable('status', 'paid');
    $view = quickSave($source, 'Ulid view');

    expect($view->user_id)->toBe($user->getKey());

    Livewire::test(ListOrders::class)
        ->assertSee('Ulid view')
        ->call('applyTableView', $view->id)
        ->assertSet('activeTableViewId', $view->id)
        ->assertSet('tableFilters.status.value', 'paid');
});

it('keeps views of two ULID users apart', function () {
    $a = ulidUser();
    $b = ulidUser();

    $this->actingAs($a);
    $view = quickSave(Livewire::test(ListOrders::class), 'Only A');

    $this->actingAs($b);

    Livewire::test(ListOrders::class)
        ->assertDontSee('Only A')
        ->call('applyTableView', $view->id)
        ->assertSet('activeTableViewId', null);
});

it('works with UUID and numeric looking string keys', function () {
    foreach ([(string) Str::uuid(), '12345678901234567890'] as $key) {
        TableView::create(['user_type' => User::class, 'user_id' => $key, 'name' => 'K '.$key, 'resource' => 'res', 'state' => []]);
    }

    expect(TableView::query()->ownedBy(User::class, (string) Str::uuid())->count())->toBe(0)
        ->and(TableView::query()->ownedBy(User::class, '12345678901234567890')->pluck('name')->all())->toBe(['K 12345678901234567890']);
});

it('resolves the user relation for a ULID user', function () {
    config(['tablekit.user.model' => UlidUser::class]);

    $user = ulidUser();
    $view = TableView::create(['user_type' => UlidUser::class, 'user_id' => $user->getKey(), 'name' => 'R', 'resource' => 'res', 'state' => []]);

    expect($view->user->is($user))->toBeTrue();
});
