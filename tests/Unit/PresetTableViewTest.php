<?php

use Elite\TableViews\Presets\PresetTableView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

it('has sane defaults', function () {
    $preset = PresetTableView::make()->key('shipped_orders');

    expect($preset->getKey())->toBe('shipped_orders')
        ->and($preset->getLabel())->toBe('Shipped Orders')
        ->and($preset->getIcon())->toBeNull()
        ->and($preset->getColor())->toBeNull()
        ->and($preset->isFavorite())->toBeFalse()
        ->and($preset->isDefault())->toBeFalse()
        ->and($preset->isVisible())->toBeTrue()
        ->and($preset->hasQuery())->toBeFalse()
        ->and($preset->getBadge())->toBeNull()
        ->and($preset->getBadgeColor())->toBe('primary')
        ->and($preset->getTooltip())->toBeNull()
        ->and($preset->getPreservedParts())->toBe([])
        ->and($preset->getDefinedParts())->toBe([]);
});

it('stores label, icon, color, favorite and tooltip', function () {
    $preset = PresetTableView::make()
        ->label('Shipped')
        ->icon('truck')
        ->color('success')
        ->favorite()
        ->tooltip('Orders on the way');

    expect($preset->getLabel())->toBe('Shipped')
        ->and($preset->getIcon())->toBe('truck')
        ->and($preset->getColor())->toBe('success')
        ->and($preset->isFavorite())->toBeTrue()
        ->and($preset->getTooltip())->toBe('Orders on the way');
});

it('accepts closures for label, favorite, default, tooltip, badge and badge color', function () {
    $preset = PresetTableView::make()
        ->label(fn () => 'Dynamic')
        ->favorite(fn () => true)
        ->default(fn () => true)
        ->tooltip(fn () => 'Tip')
        ->badge(fn () => 12)
        ->badgeColor(fn () => 'warning');

    expect($preset->getLabel())->toBe('Dynamic')
        ->and($preset->isFavorite())->toBeTrue()
        ->and($preset->isDefault())->toBeTrue()
        ->and($preset->getTooltip())->toBe('Tip')
        ->and($preset->getBadge())->toBe(12)
        ->and($preset->getBadgeColor())->toBe('warning');
});

it('ignores a badge or tooltip that is not a plain value', function () {
    $preset = PresetTableView::make()->badge(fn () => ['x'])->tooltip(fn () => 5);

    expect($preset->getBadge())->toBeNull()->and($preset->getTooltip())->toBeNull();

    expect(PresetTableView::make()->badge(0)->getBadge())->toBe(0)
        ->and(PresetTableView::make()->badge('')->getBadge())->toBeNull();
});

it('combines visible and hidden', function () {
    expect(PresetTableView::make()->visible(false)->isVisible())->toBeFalse()
        ->and(PresetTableView::make()->hidden()->isVisible())->toBeFalse()
        ->and(PresetTableView::make()->hidden(false)->isVisible())->toBeTrue()
        ->and(PresetTableView::make()->visible(fn () => false)->isVisible())->toBeFalse()
        ->and(PresetTableView::make()->visible(true)->hidden(fn () => true)->isVisible())->toBeFalse();
});

it('turns every default builder into the stored state', function () {
    $preset = PresetTableView::make()
        ->defaultFilters(['status' => ['value' => 'shipped']])
        ->defaultSort('ordered_at', 'desc')
        ->defaultSearch('foo')
        ->defaultColumnSearches(['reference' => 'ORD'])
        ->defaultToggledColumns(['notes', 'customer.city'])
        ->defaultGrouping('status', 'asc');

    $state = $preset->toState();

    expect($state->filters)->toBe(['status' => ['value' => 'shipped']])
        ->and($state->sortColumn)->toBe('ordered_at')
        ->and($state->sortDirection)->toBe('desc')
        ->and($state->search)->toBe('foo')
        ->and($state->columnSearches)->toBe(['reference' => 'ORD'])
        ->and($state->groupingColumn)->toBe('status')
        ->and($state->groupingDirection)->toBe('asc')
        ->and($preset->getDefaultToggledColumns())->toBe(['notes', 'customer.city'])
        ->and($preset->getDefinedParts())->toBe(['filters', 'search', 'sort', 'columns', 'grouping']);
});

it('reads a sort array and uses its first pair', function () {
    $state = PresetTableView::make()->defaultSort(['amount' => 'desc', 'reference' => 'asc'])->toState();

    expect($state->sortColumn)->toBe('amount')->and($state->sortDirection)->toBe('desc');
});

it('normalizes sort and grouping directions and clears them on null', function () {
    expect(PresetTableView::make()->defaultSort('a', 'DESC')->toState()->sortDirection)->toBe('desc')
        ->and(PresetTableView::make()->defaultSort('a', 'sideways')->toState()->sortDirection)->toBe('asc')
        ->and(PresetTableView::make()->defaultSort(null)->toState()->sortColumn)->toBeNull()
        ->and(PresetTableView::make()->defaultSort([])->getDefinedParts())->toBe([])
        ->and(PresetTableView::make()->defaultGrouping('a', 'bad')->toState()->groupingDirection)->toBe('asc')
        ->and(PresetTableView::make()->defaultGrouping('a')->defaultGrouping(null)->getDefinedParts())->toBe([]);
});

it('counts an empty list as defined but null as not defined', function () {
    expect(PresetTableView::make()->defaultFilters([])->getDefinedParts())->toBe(['filters'])
        ->and(PresetTableView::make()->defaultFilters([])->defaultFilters(null)->getDefinedParts())->toBe([])
        ->and(PresetTableView::make()->defaultToggledColumns([])->getDefinedParts())->toBe(['columns'])
        ->and(PresetTableView::make()->defaultColumnSearches([])->getDefinedParts())->toBe(['search']);
});

it('strips nul bytes from preset state', function () {
    $state = PresetTableView::make()->defaultSearch("a\0b")->toState();

    expect($state->search)->toBe('ab');
});

it('keeps every preserve variant', function () {
    expect(PresetTableView::make()->preserveFilters()->getPreservedParts())->toBe(['filters'])
        ->and(PresetTableView::make()->preserveToggledColumns()->getPreservedParts())->toBe(['columns'])
        ->and(PresetTableView::make()->preserveSort()->getPreservedParts())->toBe(['sort'])
        ->and(PresetTableView::make()->preserveSearch()->getPreservedParts())->toBe(['search'])
        ->and(PresetTableView::make()->preserveAll()->getPreservedParts())->toEqualCanonicalizing(PresetTableView::PARTS)
        ->and(PresetTableView::make()->preserveSort()->preserveFilters()->getPreservedParts())->toBe(['sort', 'filters'])
        ->and(PresetTableView::make()->preserveAll()->preserveAll(false)->getPreservedParts())->toBe([]);
});

it('runs the query closure with a named or typed builder and keeps the query when it returns nothing', function () {
    $model = new class extends Model
    {
        protected $table = 'orders';
    };

    $query = fn () => $model->newQuery();

    $typed = PresetTableView::make()->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'paid'));
    $named = PresetTableView::make()->modifyQueryUsing(fn ($query) => $query->where('status', 'paid'));
    $nothing = PresetTableView::make()->modifyQueryUsing(function (Builder $q): void {
        $q->where('status', 'paid');
    });

    expect($typed->hasQuery())->toBeTrue()
        ->and($typed->applyQuery($query())->toSql())->toContain('"status" = ?')
        ->and($named->applyQuery($query())->toSql())->toContain('"status" = ?')
        ->and($nothing->applyQuery($query())->toSql())->toContain('"status" = ?')
        ->and(PresetTableView::make()->applyQuery($query())->toSql())->not->toContain('where');
});

it('evaluates the badge, badge color and tooltip closures once per instance, however often they are read', function () {
    $calls = ['badge' => 0, 'color' => 0, 'tooltip' => 0];

    $preset = PresetTableView::make()
        ->badge(function () use (&$calls) {
            $calls['badge']++;

            return 3;
        })
        ->badgeColor(function () use (&$calls) {
            $calls['color']++;

            return 'danger';
        })
        ->tooltip(function () use (&$calls) {
            $calls['tooltip']++;

            return 'tip';
        });

    foreach (range(1, 3) as $read) {
        expect($preset->getBadge())->toBe(3)
            ->and($preset->getBadgeColor())->toBe('danger')
            ->and($preset->getTooltip())->toBe('tip');
    }

    expect($calls)->toBe(['badge' => 1, 'color' => 1, 'tooltip' => 1]);
});
