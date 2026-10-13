<?php

use TableKit\Models\TableView;
use TableKit\Support\TableViewState;
use TableKit\Tests\Fixtures\Team;
use TableKit\Tests\Fixtures\User;

function makeView(array $attributes = []): TableView
{
    return TableView::create([
        'user_id' => 1,
        'name' => 'View',
        'resource' => 'res',
        'state' => new TableViewState,
        ...$attributes,
    ]);
}

it('scopes by owner without a tenant', function () {
    $a = $this->signIn();
    $b = User::create(['name' => 'B', 'email' => 'b@example.test', 'password' => 'x']);
    $team = Team::create(['name' => 'T']);

    makeView(['user_id' => $a->id, 'name' => 'mine']);
    makeView(['user_id' => $b->id, 'name' => 'theirs']);
    makeView(['user_id' => $a->id, 'name' => 'tenant', 'tenant_type' => $team->getMorphClass(), 'tenant_id' => $team->id]);

    expect(TableView::forCurrentContext('res')->pluck('name')->all())->toBe(['mine']);
});

it('scopes by tenant', function () {
    $user = $this->signIn();
    $x = Team::create(['name' => 'X']);
    $y = Team::create(['name' => 'Y']);

    foreach ([$x, $y, null] as $tenant) {
        makeView([
            'user_id' => $user->id,
            'name' => $tenant?->name ?? 'none',
            'tenant_type' => $tenant?->getMorphClass(),
            'tenant_id' => $tenant?->id,
        ]);
    }

    expect(TableView::query()->ownedBy($user->id, $x)->pluck('name')->all())->toBe(['X'])
        ->and(TableView::query()->ownedBy($user->id, $y)->pluck('name')->all())->toBe(['Y'])
        ->and(TableView::query()->ownedBy($user->id, null)->pluck('name')->all())->toBe(['none']);
});

it('returns nothing when nobody is signed in', function () {
    makeView(['user_id' => 1]);

    expect(TableView::forCurrentContext('res')->count())->toBe(0)
        ->and(TableView::nameTaken('View', 'res'))->toBeFalse();
});

it('filters by resource, favorites and orders by sort then id', function () {
    $user = $this->signIn();

    makeView(['user_id' => $user->id, 'name' => 'c', 'sort' => 2]);
    makeView(['user_id' => $user->id, 'name' => 'a', 'sort' => 1]);
    makeView(['user_id' => $user->id, 'name' => 'b', 'sort' => 1]);
    makeView(['user_id' => $user->id, 'name' => 'hidden', 'sort' => 0, 'is_favorite' => false]);
    makeView(['user_id' => $user->id, 'name' => 'other', 'resource' => 'other']);

    expect(TableView::forCurrentContext('res')->favorites()->ordered()->pluck('name')->all())->toBe(['a', 'b', 'c'])
        ->and(TableView::forCurrentContext('res')->ordered()->pluck('name')->all())->toBe(['hidden', 'a', 'b', 'c'])
        ->and(TableView::forCurrentContext('other')->pluck('name')->all())->toBe(['other']);
});

it('computes the next sort per user and resource', function () {
    $user = $this->signIn();

    expect(TableView::nextSort('res'))->toBe(1);

    makeView(['user_id' => $user->id, 'sort' => 4]);
    makeView(['user_id' => 999, 'sort' => 50]);
    makeView(['user_id' => $user->id, 'sort' => 90, 'resource' => 'other']);

    expect(TableView::nextSort('res'))->toBe(5);
});

it('detects taken names case-insensitively inside the current scope only', function () {
    $user = $this->signIn();
    $team = Team::create(['name' => 'T']);

    makeView(['user_id' => $user->id, 'name' => 'My View']);
    makeView(['user_id' => 999, 'name' => 'Theirs']);
    makeView(['user_id' => $user->id, 'name' => 'Tenant only', 'tenant_type' => $team->getMorphClass(), 'tenant_id' => $team->id]);

    expect(TableView::nameTaken('my view', 'res'))->toBeTrue()
        ->and(TableView::nameTaken('  MY VIEW ', 'res'))->toBeTrue()
        ->and(TableView::nameTaken('my view', 'other'))->toBeFalse()
        ->and(TableView::nameTaken('Theirs', 'res'))->toBeFalse()
        ->and(TableView::nameTaken('Tenant only', 'res'))->toBeFalse();
});

it('falls back to primary for an unknown color', function () {
    expect(makeView(['color' => 'success'])->resolvedColor())->toBe('success')
        ->and(makeView(['color' => 'nonexistent'])->resolvedColor())->toBe('primary')
        ->and(makeView(['color' => null])->resolvedColor())->toBe('primary');
});

it('uses the configured table name', function () {
    expect((new TableView)->getTable())->toBe('tablekit_views');
});

it('resolves the user relation', function () {
    $user = $this->signIn();

    expect(makeView(['user_id' => $user->id])->user->is($user))->toBeTrue();
});
