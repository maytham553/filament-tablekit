<?php

use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Customer;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\EditCustomer;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;
use TableKit\Tests\Fixtures\RelationManagers\OrdersWithPresetsRelationManager;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    presets(fn () => [
        'big' => PresetTableView::make()
            ->label('Big')
            ->favorite()
            ->defaultFilters(['status' => ['value' => 'shipped']])
            ->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 500)),
        'hidden' => PresetTableView::make()->label('Hidden')->hidden()->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 900)),
    ]);
});

function bigShipped(): int
{
    return Order::where('status', 'shipped')->where('amount', '>=', 500)->count();
}

describe('the active view in the url', function () {
    it('mirrors the active preset and own view, and clears on Default', function () {
        $view = storeView($this->user->id, ['search' => 'one'], ['resource' => ListOrdersWithPresets::class]);

        $component = Livewire::test(ListOrdersWithPresets::class)->assertSet('tableViewUrl', null);

        $component->call('applyTablePresetView', 'big')->assertSet('tableViewUrl', 'preset:big');
        $component->call('applyTableView', $view->id)->assertSet('tableViewUrl', 'user:'.$view->id);
        $component->call('applyDefaultTableView')->assertSet('tableViewUrl', null);
    });

    it('is declared as a query string entry named view', function () {
        $effects = Livewire::test(ListOrdersWithPresets::class)->effects;

        expect($effects['url'] ?? [])->toHaveKey('tableViewUrl')
            ->and($effects['url']['tableViewUrl']['as'])->toBe('view')
            ->and($effects['url']['tableViewUrl']['use'])->toBe('replace');
    });

    it('cannot be set by the browser', function () {
        expect(fn () => Livewire::test(ListOrdersWithPresets::class)->set('tableViewUrl', 'preset:big'))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('keeps the hidden query after a reload of a preset with table state in the url', function () {
        $component = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'big');

        $query = ['view' => $component->get('tableViewUrl'), 'filters' => ['status' => ['value' => 'shipped']]];

        $reloaded = Livewire::withQueryParams($query)->test(ListOrdersWithPresets::class);

        $reloaded->assertSet('activeTablePresetKey', 'big')
            ->assertSet('activeTableViewId', null)
            ->assertSet('tableFilters.status.value', 'shipped')
            ->assertSet('tableViewUrl', 'preset:big');

        expect($reloaded->instance()->getFilteredTableQuery()->count())->toBe(bigShipped());
    });

    it('lets the table state in the url win over the preset', function () {
        $reloaded = Livewire::withQueryParams([
            'view' => 'preset:big',
            'filters' => ['status' => ['value' => 'paid']],
            'search' => 'ORD-0',
        ])->test(ListOrdersWithPresets::class);

        $reloaded->assertSet('activeTablePresetKey', 'big')
            ->assertSet('tableFilters.status.value', 'paid')
            ->assertSet('tableSearch', 'ORD-0');

        expect($reloaded->instance()->getFilteredTableQuery()->where('amount', '<', 500)->count())->toBe(0);
    });

    it('restores a preset when the url only carries the view', function () {
        $reloaded = Livewire::withQueryParams(['view' => 'preset:big'])->test(ListOrdersWithPresets::class);

        $reloaded->assertSet('activeTablePresetKey', 'big')
            ->assertSet('tableFilters.status.value', null);

        expect($reloaded->instance()->getFilteredTableQuery()->where('amount', '<', 500)->count())->toBe(0);
    });

    it('marks the restored view as modified when the url state differs, and clean when it matches', function () {
        $matching = Livewire::withQueryParams(['view' => 'preset:big', 'filters' => ['status' => ['value' => 'shipped']]])
            ->test(ListOrdersWithPresets::class);

        expect(callProtected($matching, 'isActiveTableViewModified'))->toBeFalse();

        $different = Livewire::withQueryParams(['view' => 'preset:big', 'search' => 'x'])->test(ListOrdersWithPresets::class);

        expect(callProtected($different, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('restores one of the user\'s own views', function () {
        $view = storeView($this->user->id, ['search' => 'one'], ['resource' => ListOrdersWithPresets::class]);

        Livewire::withQueryParams(['view' => 'user:'.$view->id, 'search' => 'ORD-07'])
            ->test(ListOrdersWithPresets::class)
            ->assertSet('activeTableViewId', $view->id)
            ->assertSet('tableSearch', 'ORD-07');
    });

    it('wins over the developer default when the url has no table state', function () {
        presets(fn () => [
            'dev' => PresetTableView::make()->label('Dev')->default()->defaultSearch('ORD-09'),
            'big' => PresetTableView::make()->label('Big')->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 500)),
        ]);

        Livewire::withQueryParams(['view' => 'preset:big'])
            ->test(ListOrdersWithPresets::class)
            ->assertSet('activeTablePresetKey', 'big');
    });

    it('does not remember a restored view in the session', function () {
        config(['tablekit.persist_active_view_in_session' => true]);

        Livewire::withQueryParams(['view' => 'preset:big'])->test(ListOrdersWithPresets::class);

        expect(collect(session()->all())->keys()->filter(fn ($key) => str_starts_with($key, 'tablekit.')))->toBeEmpty();
    });

    it('ignores a hidden, unknown or malformed view and falls back to the normal start', function (mixed $value) {
        $component = Livewire::withQueryParams(['view' => $value])->test(ListOrdersWithPresets::class);

        $component->assertSet('activeTablePresetKey', null)
            ->assertSet('activeTableViewId', null)
            ->assertSet('tableViewUrl', null);

        expect($component->instance()->getFilteredTableQuery()->count())->toBe(Order::count());
    })->with([
        'hidden preset' => 'preset:hidden',
        'unknown preset' => 'preset:nope',
        'unknown type' => 'view:big',
        'no type' => 'big',
        'empty' => '',
        'user view that does not exist' => 'user:99999',
        'sql' => 'user:1 OR 1=1',
        'huge number' => 'user:99999999999999999999999',
        'null byte' => "preset:\0",
        'long' => 'preset:'.str_repeat('a', 500),
        'array' => [['preset:big']],
        'nested array' => [['a' => ['b' => 'c']]],
    ]);

    it('ignores another user\'s view id', function () {
        $other = storeView($this->user->id + 1000, ['search' => 'one'], ['resource' => ListOrdersWithPresets::class, 'name' => 'Not mine']);

        Livewire::withQueryParams(['view' => 'user:'.$other->id])
            ->test(ListOrdersWithPresets::class)
            ->assertSet('activeTableViewId', null)
            ->assertSet('tableViewUrl', null);
    });

    it('ignores a view saved for another table', function () {
        $elsewhere = storeView($this->user->id, ['search' => 'one'], ['resource' => 'App\\Other\\Page', 'name' => 'Elsewhere']);

        Livewire::withQueryParams(['view' => 'user:'.$elsewhere->id])
            ->test(ListOrdersWithPresets::class)
            ->assertSet('activeTableViewId', null);
    });

    it('still applies the startup default when the view in the url is invalid and the url has no table state', function () {
        presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultSearch('ORD-09')]);

        Livewire::withQueryParams(['view' => 'preset:nope'])
            ->test(ListOrdersWithPresets::class)
            ->assertSet('activeTablePresetKey', 'dev');
    });

    it('stops applying the query when the preset turns hidden between visits', function () {
        $visible = true;
        presets(function () use (&$visible) {
            return [
                'big' => PresetTableView::make()->label('Big')->visible(fn () => $visible)->modifyQueryUsing(fn (Builder $query) => $query->where('amount', '>=', 500)),
            ];
        });

        $visible = false;

        $component = Livewire::withQueryParams(['view' => 'preset:big'])->test(ListOrdersWithPresets::class);

        expect($component->get('activeTablePresetKey'))->toBeNull()
            ->and($component->instance()->getFilteredTableQuery()->count())->toBe(Order::count());
    });

    it('is not tracked in the url on a relation manager or widget', function () {
        $customer = Customer::create(['name' => 'Acme']);

        $component = Livewire::test(OrdersWithPresetsRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ]);

        expect($component->effects['url'] ?? [])->not->toHaveKey('tableViewUrl');

        $component->call('$refresh')->assertSet('tableViewUrl', null);
    });
});
