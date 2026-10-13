<?php

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Customer;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\EditCustomer;
use TableKit\Tests\Fixtures\Pages\ListTeamOrders;
use TableKit\Tests\Fixtures\RelationManagers\OrdersWithPresetsRelationManager;
use TableKit\Tests\Fixtures\ScopedOrder;
use TableKit\Tests\Fixtures\Team;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function orWherePreset(): PresetTableView
{
    return PresetTableView::make()
        ->label('Paid or shipped')
        ->favorite()
        ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid')->orWhere('status', 'shipped'));
}

it('puts the closure in one nested group so an orWhere stays inside the preset', function () {
    $query = Order::query()->where('customer_id', 7);

    $sql = orWherePreset()->applyQuery($query)->toSql();

    expect($sql)->toContain('"customer_id" = ? and ("status" = ? or "status" = ?)');
});

it('keeps scopes and relations of the model available inside the closure', function () {
    $preset = PresetTableView::make()->modifyQueryUsing(fn (Builder $query) => $query->shipped()->orWhereHas('team'));

    $rows = $preset->applyQuery(Order::query()->where('id', '<=', 10))->pluck('id');

    expect($rows->all())->toBe(Order::where('id', '<=', 10)->where('status', 'shipped')->pluck('id')->all());
});

it('ignores what the closure returns and a closure that does nothing', function () {
    $elsewhere = PresetTableView::make()->modifyQueryUsing(fn (Builder $query) => Order::query()->where('status', 'paid'));
    $nothing = PresetTableView::make()->modifyQueryUsing(fn (Builder $query) => null);

    $base = Order::query()->where('status', 'cancelled');

    expect($elsewhere->applyQuery(clone $base)->count())->toBe(Order::where('status', 'cancelled')->count())
        ->and($nothing->applyQuery(clone $base)->count())->toBe(Order::where('status', 'cancelled')->count());
});

it('runs the closure exactly once per query build', function () {
    $calls = 0;

    $preset = PresetTableView::make()->modifyQueryUsing(function (Builder $query) use (&$calls) {
        $calls++;

        return $query->where('amount', '>=', 500);
    });

    $preset->applyQuery(Order::query());
    expect($calls)->toBe(1);

    $preset->applyQuery(Order::query());
    expect($calls)->toBe(2);
});

it('keeps the global scopes of the table query when the closure removes them', function (string $how) {
    $preset = PresetTableView::make()->modifyQueryUsing(fn (Builder $query) => ($how === 'all'
        ? $query->withoutGlobalScopes()
        : $query->withoutGlobalScope('not-cancelled'))->whereIn('status', ['paid', 'cancelled']));

    $expected = Order::where('status', 'paid')->count();

    $query = $preset->applyQuery(ScopedOrder::query());

    expect($expected)->toBeGreaterThan(0)
        ->and(Order::where('status', 'cancelled')->count())->toBeGreaterThan(0)
        ->and($query->count())->toBe($expected)
        ->and($query->pluck('status')->unique()->all())->toBe(['paid']);
})->with(['withoutGlobalScopes()' => 'all', 'withoutGlobalScope(name)' => 'named']);

it('keeps the eager loads of the closure', function () {
    $preset = PresetTableView::make()->modifyQueryUsing(fn (Builder $query) => $query->with('team'));

    expect($preset->applyQuery(Order::query())->getEagerLoads())->toHaveKey('team');
});

describe('relation manager', function () {
    beforeEach(function () {
        $this->customer = Customer::create(['name' => 'Acme']);
        $this->stranger = Customer::create(['name' => 'Other']);

        Order::query()->where('id', '<=', 30)->update(['customer_id' => $this->customer->id]);
        Order::query()->where('id', '>', 30)->update(['customer_id' => $this->stranger->id]);

        presets(fn () => ['either' => orWherePreset()]);
    });

    function presetRelationManager(Customer $customer)
    {
        return Livewire::test(OrdersWithPresetsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => EditCustomer::class]);
    }

    it('only lists the parents rows when the preset query uses orWhere', function () {
        $expected = $this->customer->orders()->whereIn('status', ['paid', 'shipped'])->get();

        expect($expected->count())->toBeGreaterThan(0)
            ->and(Order::whereIn('status', ['paid', 'shipped'])->count())->toBeGreaterThan($expected->count());

        $component = presetRelationManager($this->customer)
            ->call('applyTablePresetView', 'either')
            ->assertSet('activeTablePresetKey', 'either')
            ->assertCountTableRecords($expected->count());

        $ids = callProtected($component, 'getFilteredTableQuery')->pluck('orders.id')->sort()->values()->all();

        expect($ids)->toBe($expected->pluck('id')->sort()->values()->all());
    });

    it('does not widen the parents rows through the filtered query either', function () {
        $component = presetRelationManager($this->customer)->call('applyTablePresetView', 'either');

        $ids = callProtected($component, 'getFilteredTableQuery')->pluck('orders.id');

        expect($ids)->not->toBeEmpty()
            ->and(Order::whereIn('id', $ids)->pluck('customer_id')->unique()->all())->toBe([$this->customer->id]);
    });
});

describe('tenant scoped resource', function () {
    beforeEach(function () {
        $this->teamA = Team::create(['name' => 'A']);
        $this->teamB = Team::create(['name' => 'B']);

        Order::query()->where('id', '<=', 25)->update(['team_id' => $this->teamA->id]);
        Order::query()->where('id', '>', 25)->update(['team_id' => $this->teamB->id]);

        Filament::setCurrentPanel(Filament::getPanel('team'));
        Filament::bootCurrentPanel();
        Filament::setTenant($this->teamA);

        presets(fn () => ['either' => orWherePreset()]);
    });

    it('lists only the tenants rows without a preset', function () {
        Livewire::test(ListTeamOrders::class)->assertCountTableRecords(25);
    });

    it('only lists the tenants rows when the preset query uses orWhere', function () {
        $expected = Order::where('team_id', $this->teamA->id)->whereIn('status', ['paid', 'shipped'])->count();

        expect($expected)->toBeGreaterThan(0)
            ->and(Order::whereIn('status', ['paid', 'shipped'])->count())->toBeGreaterThan($expected);

        $component = Livewire::test(ListTeamOrders::class)
            ->call('applyTablePresetView', 'either')
            ->assertCountTableRecords($expected);

        $teams = callProtected($component, 'getFilteredTableQuery')->pluck('team_id')->unique()->all();

        expect($teams)->toBe([$this->teamA->id]);
    });

    it('keeps the tenant after a search and a sort on top of the preset', function () {
        $component = Livewire::test(ListTeamOrders::class)
            ->call('applyTablePresetView', 'either')
            ->searchTable('ORD-0')
            ->sortTable('amount', 'desc');

        $teams = callProtected($component, 'getFilteredSortedTableQuery')->pluck('team_id')->unique()->all();

        expect($teams)->toBe([$this->teamA->id]);
    });
});
