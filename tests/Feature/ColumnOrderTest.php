<?php

use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\Tests\Fixtures\Customer;
use Elite\TableViews\Tests\Fixtures\Order;
use Elite\TableViews\Tests\Fixtures\Pages\EditCustomer;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersColumnGroups;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersNoToggles;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersOneColumn;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3Off;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPhase3ToggleGrid;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersRich;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersSplit;
use Elite\TableViews\Tests\Fixtures\RelationManagers\OrdersRelationManager;
use Elite\TableViews\Tests\Fixtures\Widgets\OrdersWidget;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function visibleOrderOf($component): array
{
    return callProtected($component, 'getTableViewOrderableColumnNames');
}

describe('moving columns', function () {
    it('keeps the code order until a column is moved', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        expect(headerOrder($component->html()))->toBe(CODE_HEADERS)
            ->and($component->get('tableViewColumnOrder'))->toBe([]);
    });

    it('moves a column up in headers and cells, in the same request', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect(headerOrder($component->html()))->toBe(['reference', 'customer-name', 'amount', 'status', 'team', 'ordered-at', 'plain'])
            ->and(firstRowOrder($component->html()))->toBe(['reference', 'customer-name', 'amount', 'status', 'team', 'ordered-at', 'plain']);
    });

    it('moves a column down', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'reference', 'down');

        expect(headerOrder($component->html()))->toBe(['customer-name', 'reference', 'status', 'amount', 'team', 'ordered-at', 'plain']);
    });

    it('keeps the order on later requests', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('reorderTableViewColumn', 'plain', 'up')
            ->call('reorderTableViewColumn', 'plain', 'up')
            ->searchTable('ORD')
            ->call('gotoPage', 1)
            ->sortTable('amount');

        expect(headerOrder($component->html()))->toBe(['reference', 'customer-name', 'status', 'amount', 'team', 'plain', 'ordered-at']);
    });

    it('does nothing at the edges', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('reorderTableViewColumn', 'reference', 'up')
            ->call('reorderTableViewColumn', 'plain', 'down');

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and(headerOrder($component->html()))->toBe(CODE_HEADERS);
    });

    it('steps over a column the developer hides', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'team.name', 'up');

        expect($component->get('tableViewColumnOrder'))->toBe(['reference', 'customer_name', 'status', 'team.name', 'internal', 'amount', 'notes', 'ordered_at', 'plain'])
            ->and(headerOrder($component->html()))->toBe(['reference', 'customer-name', 'status', 'team', 'amount', 'ordered-at', 'plain']);
    });

    it('moves a column that is toggled hidden and shows it where it was put', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('reorderTableViewColumn', 'notes', 'up')
            ->set('toggledTableColumns.notes', true);

        expect(headerOrder($component->html()))->toBe(['reference', 'customer-name', 'status', 'amount', 'notes', 'team', 'ordered-at', 'plain']);
    });

    it('does not touch which columns are shown', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->set('toggledTableColumns.ordered_at', false)
            ->call('reorderTableViewColumn', 'amount', 'up');

        expect(headerOrder($component->html()))->not->toContain('ordered-at')->not->toContain('notes')
            ->and($component->get('toggledTableColumns.ordered_at'))->toBeFalse();
    });

    it('stores the order as a full list and an empty list once it is the code order again', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect($component->get('tableViewColumnOrder'))->toHaveCount(9);

        $component->call('reorderTableViewColumn', 'amount', 'down');

        expect($component->get('tableViewColumnOrder'))->toBe([]);
    });

    it('resets to the code order', function () {
        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('reorderTableViewColumn', 'amount', 'up')
            ->call('reorderTableViewColumn', 'plain', 'up')
            ->call('resetTableViewColumnOrder');

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and(headerOrder($component->html()))->toBe(CODE_HEADERS);

        expect(headerOrder(Livewire::test(ListOrdersPhase3::class)->html()))->toBe(CODE_HEADERS);
    });

    it('ignores bad names and directions', function (mixed $name, mixed $direction) {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', $name, $direction);

        expect($component->get('tableViewColumnOrder'))->toBe([]);
    })->with([
        'unknown' => ['nope', 'up'],
        'hidden by the developer' => ['internal', 'up'],
        'empty' => ['', 'up'],
        'array' => [['amount'], 'up'],
        'int' => [3, 'down'],
        'null' => [null, 'up'],
        'bad direction' => ['amount', 'left'],
        'array direction' => ['amount', ['up']],
        'null direction' => ['amount', null],
        'huge' => [str_repeat('a', 5000), 'up'],
    ]);

    it('cannot be set by the browser', function () {
        expect(fn () => Livewire::test(ListOrdersPhase3::class)->set('tableViewColumnOrder', ['amount', 'reference']))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });
});

describe('drag reorder', function () {
    it('stores the dragged order and applies it to headers and cells in the same request', function () {
        $order = visibleOrderOf(Livewire::test(ListOrdersPhase3::class));
        $dragged = [$order[3], ...array_values(array_diff($order, [$order[3]]))];

        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumns', $dragged);

        expect(visibleOrderOf($component))->toBe($dragged)
            ->and(headerOrder($component->html())[0])->toBe('amount')
            ->and(firstRowOrder($component->html())[0])->toBe('amount')
            ->and($component->get('tableViewColumnOrder'))->not->toBe([]);
    });

    it('keeps hidden columns where they were and survives later requests', function () {
        $order = visibleOrderOf(Livewire::test(ListOrdersPhase3::class));
        $dragged = array_reverse($order);

        $component = Livewire::test(ListOrdersPhase3::class)
            ->call('reorderTableViewColumns', $dragged)
            ->searchTable('ORD')
            ->sortTable('amount');

        expect(visibleOrderOf($component))->toBe($dragged)
            ->and($component->get('tableViewColumnOrder'))->toContain('internal');
    });

    it('stores nothing when the dragged order is the code order', function () {
        $order = visibleOrderOf(Livewire::test(ListOrdersPhase3::class));

        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumns', $order);

        expect($component->get('tableViewColumnOrder'))->toBe([]);
    });

    it('moves a column by keyboard through the same order call', function () {
        $order = visibleOrderOf(Livewire::test(ListOrdersPhase3::class));
        $moved = $order;
        [$moved[1], $moved[2]] = [$moved[2], $moved[1]];

        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumns', $moved);

        expect(visibleOrderOf($component))->toBe($moved);

        $component->call('reorderTableViewColumns', $order);

        expect(visibleOrderOf($component))->toBe($order)->and($component->get('tableViewColumnOrder'))->toBe([]);
    });

    it('ignores a tampered order and never errors', function (mixed $names) {
        $order = visibleOrderOf(Livewire::test(ListOrdersPhase3::class));

        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumns', $names)->assertSuccessful();

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and(visibleOrderOf($component))->toBe($order);
    })->with([
        'missing' => [['customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', 'ordered_at']],
        'empty' => [[]],
        'extra unknown' => [['plain', 'reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'ordered_at', 'nope']],
        'extra developer hidden' => [['internal', 'reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', 'ordered_at']],
        'hidden instead of a column' => [['internal', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', 'ordered_at']],
        'duplicate' => [['reference', 'reference', 'status', 'amount', 'team.name', 'notes', 'plain', 'ordered_at']],
        'unknown name' => [['nope', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', 'ordered_at']],
        'oversized' => [array_map(fn (int $i): string => "c{$i}", range(1, 5000))],
        'huge name' => [['reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', str_repeat('a', 100000)]],
        'nested' => [['reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', ['ordered_at']]],
        'null entry' => [['reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', null]],
        'bool entry' => [['reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', true]],
        'float entry' => [['reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', 1.5]],
        'string' => ['reference,customer_name,status'],
        'null' => [null],
        'keyed' => [['a' => 'reference', 'b' => 'customer_name', 'c' => 'status', 'd' => 'amount', 'e' => 'team.name', 'f' => 'notes', 'g' => 'plain', 'h' => 'ordered_at']],
    ]);

    it('does nothing when column ordering is off or unsupported', function () {
        $off = Livewire::test(ListOrdersPhase3Off::class);
        $off->call('reorderTableViewColumns', array_reverse(visibleOrderOf($off)))->assertSuccessful();

        $groups = Livewire::test(ListOrdersColumnGroups::class)->call('reorderTableViewColumns', ['amount', 'reference'])->assertSuccessful();

        expect($off->get('tableViewColumnOrder'))->toBe([])->and($groups->get('tableViewColumnOrder'))->toBe([]);
    });
});

describe('new and removed columns', function () {
    it('appends columns the saved order does not know, in code order', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        expect(callProtected($component, 'resolveTableViewColumnOrder', ['amount', 'reference']))
            ->toBe(['amount', 'reference', 'customer_name', 'status', 'internal', 'team.name', 'notes', 'ordered_at', 'plain']);
    });

    it('ignores saved columns that no longer exist, duplicates and junk', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        expect(callProtected($component, 'resolveTableViewColumnOrder', ['gone', 'amount', 'amount', ['x'], null, 7, 'plain']))
            ->toBe(['amount', 'plain', 'reference', 'customer_name', 'status', 'internal', 'team.name', 'notes', 'ordered_at']);
    });

    it('renders a stored order that lacks a new column and has a removed one', function () {
        $component = Livewire::test(ListOrdersPhase3::class);

        session()->put(callProtected($component, 'getTableViewSessionKey', 'column_order'), ['status', 'removed', 'reference']);

        expect(headerOrder(Livewire::test(ListOrdersPhase3::class)->html()))->toBe(['status', 'reference', 'customer-name', 'amount', 'team', 'ordered-at', 'plain']);
    });
});

describe('persistence', function () {
    it('survives a new page load through the session', function () {
        Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up');

        $again = Livewire::test(ListOrdersPhase3::class);

        expect(headerOrder($again->html()))->toBe(['reference', 'customer-name', 'amount', 'status', 'team', 'ordered-at', 'plain'])
            ->and($again->get('tableViewColumnOrder'))->toHaveCount(9);
    });

    it('ignores a session value that is not a list', function () {
        $component = Livewire::test(ListOrdersPhase3::class);
        session()->put(callProtected($component, 'getTableViewSessionKey', 'column_order'), 'oops');

        expect(headerOrder(Livewire::test(ListOrdersPhase3::class)->html()))->toBe(CODE_HEADERS);
    });

    it('is kept per table', function () {
        Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect(headerOrder(Livewire::test(ListOrdersNoToggles::class)->html()))->toBe(['reference', 'status', 'amount']);
    });
});

describe('the column list', function () {
    it('replaces the content of Filament\'s own toggle dropdown', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->html();

        expect($html)->toContain('fi-ta-col-toggle')
            ->and($html)->toContain('fi-table-views-columns')
            ->and($html)->not->toContain('fi-table-views-columns-fallback');

        foreach (['reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'ordered_at', 'plain'] as $name) {
            expect($html)->toContain('data-column-row="'.$name.'"');
        }

        expect($html)->not->toContain('data-column-row="internal"');
    });

    it('follows the table\'s columnToggleFormColumns()', function () {
        $html = Livewire::test(ListOrdersPhase3ToggleGrid::class)->html();

        preg_match('/<div[^>]*fi-table-views-columns[^>]*>/', $html, $grid);

        expect($grid[0] ?? '')->toContain('grid-cols-[--cols-default]')
            ->and($grid[0])->toContain('md:grid-cols-[--cols-md]')
            ->and($grid[0])->toContain('lg:grid-cols-[--cols-lg]')
            ->and($grid[0])->toContain('--cols-md: repeat(2, minmax(0, 1fr))')
            ->and($grid[0])->toContain('--cols-lg: repeat(3, minmax(0, 1fr))');
    });

    it('keeps one column when the table sets none', function () {
        preg_match('/<div[^>]*fi-table-views-columns[^>]*>/', Livewire::test(ListOrdersPhase3::class)->html(), $grid);

        expect($grid[0] ?? '')->toContain('--cols-lg: repeat(1, minmax(0, 1fr))')->not->toContain('--cols-md');
    });

    it('binds a checkbox to every toggleable column and none to the others', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->html();

        expect($html)->toContain('wire:model.live="toggledTableColumns.notes"')
            ->and($html)->toContain('wire:model.live="toggledTableColumns.team.name"')
            ->and($html)->not->toContain('wire:model.live="toggledTableColumns.reference"')
            ->and($html)->not->toContain('wire:model.live="toggledTableColumns.plain"');
    });

    it('lists the columns in the current order', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'plain', 'up')->html();

        preg_match_all('/data-column-row="([^"]+)"/', $html, $matches);

        expect($matches[1])->toBe(['reference', 'customer_name', 'status', 'amount', 'team.name', 'notes', 'plain', 'ordered_at']);
    });

    it('shows the reset link only for a custom order', function () {
        expect(Livewire::test(ListOrdersPhase3::class)->html())->not->toContain('resetTableViewColumnOrder')
            ->and(Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'plain', 'up')->html())->toContain('resetTableViewColumnOrder');
    });

    it('renders a handle and a sortable item for every column and no arrow buttons', function () {
        $html = Livewire::test(ListOrdersPhase3::class)->html();

        preg_match_all('/data-column-row="([^"]+)"/', $html, $rows);
        preg_match_all('/x-sortable-item="([^"]+)"/', $html, $items);

        expect($items[1])->toBe($rows[1])
            ->and($html)->toContain("\$call('reorderTableViewColumns'")
            ->and(substr_count($html, 'data-table-views-reorder-handle'))->toBeGreaterThanOrEqual(count($rows[1]))
            ->and($html)->toContain(__('table-views::reorder.handle', ['name' => 'Reference']))
            ->and($html)->toContain('x-on:keydown.arrow-up.prevent')
            ->and($html)->not->toContain('wire:click="reorderTableViewColumn(');
    });

    it('still saves the toggles to the session like Filament', function () {
        Livewire::test(ListOrdersPhase3::class)->set('toggledTableColumns.notes', true);

        expect(Livewire::test(ListOrdersPhase3::class)->get('toggledTableColumns.notes'))->toBeTrue();
    });

    it('renders a fallback dropdown when no column is toggleable', function () {
        $html = Livewire::test(ListOrdersNoToggles::class)->html();

        expect($html)->toContain('fi-table-views-columns-fallback')
            ->and($html)->toContain('data-column-row="status"')
            ->and($html)->not->toContain('fi-ta-col-toggle');
    });

    it('moves columns of a table without toggles', function () {
        $component = Livewire::test(ListOrdersNoToggles::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect(headerOrder($component->html()))->toBe(['reference', 'amount', 'status']);
    });

    it('is not rendered for fewer than two columns', function () {
        $html = Livewire::test(ListOrdersOneColumn::class)->html();

        expect($html)->not->toContain('data-column-row')->and($html)->not->toContain('fi-table-views-columns-fallback')->and($html)->toContain('fi-ta-col-toggle');
    });
});

describe('leaving the table working', function () {
    beforeEach(function () {
        ListOrdersRich::$peeked = [];
        ListOrdersRich::$marked = [];
    });

    it('keeps the column action', function () {
        $component = Livewire::test(ListOrdersRich::class)
            ->call('reorderTableViewColumn', 'amount', 'up')
            ->call('reorderTableViewColumn', 'reference', 'down');

        $record = Order::query()->orderBy('id')->first();

        $component->callTableColumnAction('reference', $record);

        expect(ListOrdersRich::$peeked)->toBe([$record->reference]);
    });

    it('keeps the summary', function () {
        $component = Livewire::test(ListOrdersRich::class)->call('reorderTableViewColumn', 'amount', 'up');

        $component->assertTableColumnSummarySet('amount', 'sum', Order::query()->sum('amount'));

        expect(headerOrder($component->html()))->toBe(['reference', 'amount', 'status']);
    });

    it('keeps bulk selection and bulk actions', function () {
        $records = Order::query()->orderBy('id')->take(3)->get();

        Livewire::test(ListOrdersRich::class)
            ->call('reorderTableViewColumn', 'status', 'up')
            ->callTableBulkAction('mark', $records);

        expect(ListOrdersRich::$marked)->toBe($records->pluck('reference')->all());
    });

    it('keeps the record urls', function () {
        $first = Order::query()->orderBy('id')->first();

        $html = Livewire::test(ListOrdersRich::class)->call('reorderTableViewColumn', 'amount', 'up')->html();

        expect($html)->toContain('/orders/'.$first->getKey());
    });

    it('keeps grouping and the order of the columns', function () {
        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up')->set('tableGrouping', 'status');

        expect(headerOrder($component->html()))->toBe(['reference', 'customer-name', 'amount', 'status', 'team', 'ordered-at', 'plain'])
            ->and($component->html())->toContain('Status');
    });

    it('keeps search and column searches', function () {
        $component = Livewire::test(ListOrdersRich::class)->call('reorderTableViewColumn', 'status', 'up')->searchTable('ORD-01');

        $component->assertCountTableRecords(10);
    });

    it('works on a relation manager and a table widget', function () {
        $widget = Livewire::test(OrdersWidget::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect(headerOrder($widget->html()))->toBe(['reference', 'amount', 'status']);

        $customer = Customer::create(['name' => 'Acme']);
        Order::query()->limit(5)->update(['customer_id' => $customer->id]);

        $manager = Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => EditCustomer::class])
            ->call('reorderTableViewColumn', 'amount', 'up');

        expect(headerOrder($manager->html()))->toBe(['reference', 'amount', 'status']);
    });
});

describe('unsupported tables', function () {
    it('leaves column groups alone', function () {
        $component = Livewire::test(ListOrdersColumnGroups::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and($component->html())->not->toContain('data-column-row');
    });

    it('leaves layout components alone', function () {
        $component = Livewire::test(ListOrdersSplit::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and($component->html())->not->toContain('data-column-row');
    });
});

describe('switches', function () {
    it('is off per table and keeps Filament\'s own toggle form', function () {
        $component = Livewire::test(ListOrdersPhase3Off::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect($component->get('tableViewColumnOrder'))->toBe([])
            ->and(headerOrder($component->html()))->toBe(CODE_HEADERS)
            ->and($component->html())->not->toContain('data-column-row')
            ->and($component->html())->toContain('id="toggledTableColumns.notes"');
    });

    it('is off globally in config', function () {
        config(['table-views.column_reordering.enabled' => false]);

        $component = Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up');

        expect($component->get('tableViewColumnOrder'))->toBe([])->and($component->html())->not->toContain('data-column-row');
    });

    it('is off by the plugin', function () {
        TableViewsPlugin::get()->columnReordering(false);

        expect(Livewire::test(ListOrdersPhase3::class)->html())->not->toContain('data-column-row');
    });

    it('ignores a stored order while off', function () {
        Livewire::test(ListOrdersPhase3::class)->call('reorderTableViewColumn', 'amount', 'up');

        config(['table-views.column_reordering.enabled' => false]);

        expect(headerOrder(Livewire::test(ListOrdersPhase3::class)->html()))->toBe(CODE_HEADERS);
    });
});
