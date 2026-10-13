<?php

use TableKit\Models\TableView;
use TableKit\Presets\PresetTableView;
use TableKit\Support\TableViewState;

const OLD_PHASE_ONE_JSON = '{"v":1,"filters":{"status":{"value":"paid"}},"search":"acme","columnSearches":{},"sort":{"column":"amount","direction":"desc"},"toggledColumns":{"notes":false},"grouping":{"column":"status","direction":"asc"}}';

it('reads a view saved by Phase 1 and writes it back byte for byte', function () {
    $state = TableViewState::fromArray(json_decode(OLD_PHASE_ONE_JSON, true));

    expect($state->toJson())->toBe(OLD_PHASE_ONE_JSON)
        ->and($state->sorts)->toBe([])
        ->and($state->columnOrder)->toBe([])
        ->and($state->favoriteFilters)->toBeNull()
        ->and($state->sortEntries())->toBe([['column' => 'amount', 'direction' => 'desc']]);
});

it('keeps the version at 1 and leaves the new keys out until they are used', function () {
    $keys = array_keys((new TableViewState)->toArray());

    expect($keys)->toBe(['v', 'filters', 'search', 'columnSearches', 'sort', 'toggledColumns', 'grouping'])
        ->and((new TableViewState)->version)->toBe(1);
});

it('round trips sorts, column order and favorite filters', function () {
    $state = TableViewState::fromArray([
        'sorts' => [['column' => 'amount', 'direction' => 'desc'], ['column' => 'reference', 'direction' => 'asc']],
        'columnOrder' => ['amount', 'reference'],
        'favoriteFilters' => ['status'],
    ]);

    $again = TableViewState::fromArray(json_decode($state->toJson(), true));

    expect($again->toArray())->toBe($state->toArray())
        ->and($again->toArray()['sort'])->toBe(['column' => 'amount', 'direction' => 'desc'])
        ->and($again->toArray()['sorts'])->toHaveCount(2)
        ->and($again->columnOrder)->toBe(['amount', 'reference'])
        ->and($again->favoriteFilters)->toBe(['status']);
});

it('writes the sorts only from two entries on', function () {
    expect(TableViewState::fromArray(['sorts' => [['column' => 'a', 'direction' => 'asc']]])->toArray())->not->toHaveKey('sorts');
});

it('lets the sorts win over a disagreeing sort key', function () {
    $state = TableViewState::fromArray([
        'sort' => ['column' => 'zzz', 'direction' => 'asc'],
        'sorts' => [['column' => 'a', 'direction' => 'desc'], ['column' => 'b', 'direction' => 'asc']],
    ]);

    expect($state->sortColumn)->toBe('a')->and($state->sortDirection)->toBe('desc');
});

it('keeps an explicit empty favorite list apart from no list', function () {
    expect(TableViewState::fromArray(['favoriteFilters' => []])->favoriteFilters)->toBe([])
        ->and(TableViewState::fromArray(['favoriteFilters' => []])->toArray())->toHaveKey('favoriteFilters', [])
        ->and(TableViewState::fromArray([])->favoriteFilters)->toBeNull()
        ->and(TableViewState::fromArray(['favoriteFilters' => 'oops'])->favoriteFilters)->toBeNull()
        ->and(json_encode(TableViewState::fromArray(['favoriteFilters' => []])->jsonSerialize()))->toContain('"favoriteFilters":[]');
});

it('cleans garbage in the new keys', function () {
    $state = TableViewState::fromArray([
        'sorts' => 'oops',
        'columnOrder' => ['a', ['x'], 5, null, '', 'a', str_repeat('q', 300), "b\0c"],
        'favoriteFilters' => [['x'], 'f', 9, 'f', ''],
    ]);

    expect($state->sorts)->toBe([])
        ->and($state->columnOrder)->toBe(['a', 'bc'])
        ->and($state->favoriteFilters)->toBe(['f']);
});

it('cleans sort entries: drops duplicates, non-arrays and empty names, normalises directions', function () {
    $state = TableViewState::fromArray(['sorts' => [
        ['column' => 'a', 'direction' => 'DESC'],
        ['column' => 'a', 'direction' => 'asc'],
        'oops',
        ['column' => ''],
        ['column' => ['x']],
        ['column' => 'b', 'direction' => 'desc'],
        ['column' => 'c'],
        ['column' => str_repeat('z', 300)],
    ]]);

    expect($state->sorts)->toBe([
        ['column' => 'a', 'direction' => 'asc'],
        ['column' => 'b', 'direction' => 'desc'],
        ['column' => 'c', 'direction' => 'asc'],
    ]);
});

it('strips NUL bytes in the new keys so jsonb accepts them', function () {
    $state = TableViewState::fromArray(['sorts' => [['column' => "a\0b", 'direction' => 'asc']], 'favoriteFilters' => ["x\0y"]]);

    expect($state->sorts[0]['column'])->toBe('ab')->and($state->favoriteFilters)->toBe(['xy']);
});

it('changes the hash when any new part changes', function () {
    $base = TableViewState::fromArray(['sorts' => [['column' => 'a', 'direction' => 'asc'], ['column' => 'b', 'direction' => 'asc']], 'columnOrder' => ['a', 'b'], 'favoriteFilters' => ['f']]);

    foreach ([
        ['sorts' => [['column' => 'b', 'direction' => 'asc'], ['column' => 'a', 'direction' => 'asc']]],
        ['sorts' => [['column' => 'a', 'direction' => 'asc'], ['column' => 'b', 'direction' => 'desc']]],
        ['columnOrder' => ['b', 'a']],
        ['columnOrder' => []],
        ['favoriteFilters' => ['g']],
        ['favoriteFilters' => null],
        ['favoriteFilters' => []],
    ] as $change) {
        expect(TableViewState::fromArray([...$base->toArray(), ...$change])->hash())->not->toBe($base->hash());
    }

    expect(TableViewState::fromArray($base->toArray())->hash())->toBe($base->hash());
});

it('goes through the model cast', function () {
    $user = $this->signIn();

    $view = TableView::create([
        'user_id' => $user->id,
        'name' => 'Phase 3',
        'resource' => 'x',
        'state' => TableViewState::fromArray(['sorts' => [['column' => 'a', 'direction' => 'desc'], ['column' => 'b', 'direction' => 'asc']], 'columnOrder' => ['b', 'a'], 'favoriteFilters' => []]),
    ]);

    $fresh = TableView::find($view->id)->state;

    expect($fresh->sortEntries())->toHaveCount(2)->and($fresh->columnOrder)->toBe(['b', 'a'])->and($fresh->favoriteFilters)->toBe([]);
});

describe('preset builder', function () {
    it('keeps every pair of a multi-sort and the first as the single sort', function () {
        $state = PresetTableView::make()->defaultSort(['amount' => 'DESC', 'ref' => 'asc', 'x' => 'weird'])->toState();

        expect($state->sortEntries())->toBe([
            ['column' => 'amount', 'direction' => 'desc'],
            ['column' => 'ref', 'direction' => 'asc'],
            ['column' => 'x', 'direction' => 'asc'],
        ])->and($state->sortColumn)->toBe('amount');
    });

    it('still accepts a column and a direction', function () {
        $state = PresetTableView::make()->defaultSort('amount', 'desc')->toState();

        expect($state->sortEntries())->toBe([['column' => 'amount', 'direction' => 'desc']])->and($state->toArray())->not->toHaveKey('sorts');
    });

    it('ignores list entries without a column name and empty values', function () {
        expect(PresetTableView::make()->defaultSort(['amount', 'ref' => 'desc'])->toState()->sortEntries())->toBe([['column' => 'ref', 'direction' => 'desc']])
            ->and(PresetTableView::make()->defaultSort(null)->toState()->sortEntries())->toBe([])
            ->and(PresetTableView::make()->defaultSort('')->getDefinedParts())->toBe([]);
    });

    it('defines columns, order and favorites', function () {
        $preset = PresetTableView::make()->defaultColumns(['b', 'a'])->defaultFavoriteFilters(['f', 'f', 'g']);

        expect($preset->toState()->columnOrder)->toBe(['b', 'a'])
            ->and($preset->toState()->favoriteFilters)->toBe(['f', 'g'])
            ->and($preset->getDefaultToggledColumns())->toBe(['b', 'a'])
            ->and($preset->getDefinedParts())->toBe(['filters', 'columns']);
    });

    it('leaves the parts undefined without them', function () {
        expect(PresetTableView::make()->defaultFavoriteFilters(null)->getDefinedParts())->toBe([])
            ->and(PresetTableView::make()->toState()->favoriteFilters)->toBeNull()
            ->and(PresetTableView::make()->toState()->columnOrder)->toBe([]);
    });
});
