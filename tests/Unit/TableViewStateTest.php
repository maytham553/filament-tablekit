<?php

use TableKit\Models\TableView;
use TableKit\Support\TableViewState;
use TableKit\Tests\Fixtures\User;

function sampleState(): TableViewState
{
    return TableViewState::fromArray([
        'v' => 1,
        'filters' => ['status' => ['value' => 'paid'], 'ordered' => ['from' => '2026-01-01', 'until' => null]],
        'search' => 'acme',
        'columnSearches' => ['reference' => 'ORD-0', 'customer' => ['name' => 'john']],
        'sort' => ['column' => 'amount', 'direction' => 'desc'],
        'toggledColumns' => ['notes' => false, 'ordered_at' => true],
        'grouping' => ['column' => 'status', 'direction' => 'asc'],
    ]);
}

it('round trips every state part through toArray and fromArray', function () {
    $state = sampleState();

    expect($state->filters)->toBe(['status' => ['value' => 'paid'], 'ordered' => ['from' => '2026-01-01', 'until' => null]])
        ->and($state->search)->toBe('acme')
        ->and($state->columnSearches)->toBe(['reference' => 'ORD-0', 'customer' => ['name' => 'john']])
        ->and($state->sortColumn)->toBe('amount')
        ->and($state->sortDirection)->toBe('desc')
        ->and($state->toggledColumns)->toBe(['notes' => false, 'ordered_at' => true])
        ->and($state->groupingColumn)->toBe('status')
        ->and($state->groupingDirection)->toBe('asc');

    expect(TableViewState::fromArray($state->toArray())->toArray())->toBe($state->toArray());
});

it('tolerates missing keys and ignores unknown ones', function () {
    $state = TableViewState::fromArray(['future' => ['x' => 1], 'v' => 7]);

    expect($state->filters)->toBe([])
        ->and($state->search)->toBe('')
        ->and($state->columnSearches)->toBe([])
        ->and($state->sortColumn)->toBeNull()
        ->and($state->toggledColumns)->toBe([])
        ->and($state->groupingColumn)->toBeNull()
        ->and($state->version)->toBe(7)
        ->and($state->toArray())->not->toHaveKey('future');
});

it('survives garbage values', function () {
    $state = TableViewState::fromArray(['filters' => 'oops', 'sort' => 'oops', 'grouping' => 5, 'search' => ['a'], 'v' => 'x']);

    expect($state->filters)->toBe([])
        ->and($state->sortColumn)->toBeNull()
        ->and($state->groupingColumn)->toBeNull()
        ->and($state->search)->toBe('')
        ->and($state->version)->toBe(1);
});

it('serializes empty maps as objects so jsonb stores {}', function () {
    $json = json_encode((new TableViewState)->jsonSerialize());

    expect($json)->toContain('"filters":{}')
        ->and($json)->toContain('"columnSearches":{}')
        ->and($json)->toContain('"toggledColumns":{}')
        ->and($json)->toContain('"sort":{"column":null,"direction":null}')
        ->and($json)->not->toContain('"tab"');
});

it('emits the versioned plain json shape', function () {
    $decoded = json_decode(json_encode(sampleState()), true);

    expect(array_keys($decoded))->toBe(['v', 'filters', 'search', 'columnSearches', 'sort', 'toggledColumns', 'grouping'])
        ->and($decoded['v'])->toBe(1)
        ->and($decoded['sort'])->toBe(['column' => 'amount', 'direction' => 'desc']);
});

it('keeps the tab only when set', function () {
    $state = TableViewState::fromArray(['tab' => 'paid']);

    expect($state->toArray()['tab'])->toBe('paid')
        ->and(TableViewState::fromArray($state->toArray())->tab)->toBe('paid');
});

it('hashes independent of key order and differs on any part change', function () {
    $base = sampleState();
    $reordered = TableViewState::fromArray([
        'grouping' => ['direction' => 'asc', 'column' => 'status'],
        'toggledColumns' => ['ordered_at' => true, 'notes' => false],
        'sort' => ['direction' => 'desc', 'column' => 'amount'],
        'columnSearches' => ['customer' => ['name' => 'john'], 'reference' => 'ORD-0'],
        'search' => 'acme',
        'filters' => ['ordered' => ['until' => null, 'from' => '2026-01-01'], 'status' => ['value' => 'paid']],
    ]);

    expect($reordered->hash())->toBe($base->hash());

    $changes = [
        ['filters' => ['status' => ['value' => 'pending']]],
        ['search' => 'other'],
        ['columnSearches' => ['reference' => 'ORD-1']],
        ['sort' => ['column' => 'amount', 'direction' => 'asc']],
        ['toggledColumns' => ['notes' => true, 'ordered_at' => true]],
        ['grouping' => ['column' => 'customer_name', 'direction' => 'asc']],
        ['grouping' => ['column' => 'status', 'direction' => 'desc']],
        ['tab' => 'paid'],
    ];

    foreach ($changes as $change) {
        expect(TableViewState::fromArray([...$base->toArray(), ...$change])->hash())->not->toBe($base->hash());
    }
});

it('round trips through the model cast including empty maps', function () {
    $user = $this->signIn();

    $view = TableView::create([
        'user_type' => User::class,
        'user_id' => $user->id,
        'name' => 'Empty',
        'resource' => 'x',
        'state' => new TableViewState,
    ]);

    $raw = $view->getRawOriginal('state');

    expect(json_decode($raw)->filters)->toBeObject()
        ->and(json_decode($raw)->toggledColumns)->toBeObject()
        ->and($raw)->toContain('"filters":{}');

    $fresh = TableView::find($view->id);

    expect($fresh->state)->toBeInstanceOf(TableViewState::class)
        ->and($fresh->state->toArray())->toBe((new TableViewState)->toArray());
});

it('stores a full state exactly and accepts a plain array', function () {
    $user = $this->signIn();
    $state = sampleState();

    $a = TableView::create(['user_type' => User::class, 'user_id' => $user->id, 'name' => 'A', 'resource' => 'x', 'state' => $state]);
    $b = TableView::create(['user_type' => User::class, 'user_id' => $user->id, 'name' => 'B', 'resource' => 'x', 'state' => $state->toArray()]);

    expect(TableView::find($a->id)->state->toArray())->toBe($state->toArray())
        ->and(TableView::find($b->id)->state->toArray())->toBe($state->toArray())
        ->and(json_decode($a->getRawOriginal('state'), true))->toBe(json_decode($b->getRawOriginal('state'), true));
});

it('rejects a state of the wrong type', function () {
    $user = $this->signIn();

    TableView::create(['user_type' => User::class, 'user_id' => $user->id, 'name' => 'A', 'resource' => 'x', 'state' => 'nope']);
})->throws(InvalidArgumentException::class);

it('reads a null or empty stored state as an empty state', function () {
    $user = $this->signIn();
    $view = TableView::create(['user_type' => User::class, 'user_id' => $user->id, 'name' => 'A', 'resource' => 'x', 'state' => new TableViewState]);

    $view->setRawAttributes(['state' => '']);

    expect($view->state->toArray())->toBe((new TableViewState)->toArray());
});

it('strips NUL bytes from every string and key', function () {
    $state = TableViewState::fromArray([
        'search' => "a\0b",
        'filters' => ["st\0atus" => ['value' => "pa\0id", 'list' => ["x\0", 3]]],
        'columnSearches' => ['ref' => "\0"],
    ]);

    expect($state->search)->toBe('ab')
        ->and($state->filters)->toBe(['status' => ['value' => 'paid', 'list' => ['x', 3]]])
        ->and($state->columnSearches)->toBe(['ref' => ''])
        ->and($state->toJson())->not->toContain('\u0000');
});

it('serializes to the stored bytes', function () {
    $state = TableViewState::fromArray(['search' => 'عرض']);

    expect($state->toJson())->toContain('عرض')->toContain('"filters":{}');
});
