<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function sqliteConnections(string ...$names): void
{
    foreach ($names as $name) {
        config(["database.connections.{$name}" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    }
}

function runViewsMigration(): void
{
    (require __DIR__.'/../../database/migrations/2026_01_01_000000_create_table_views_table.php')->up();
}

it('keeps the model on the configured connection while the default is another one', function () {
    sqliteConnections('views', 'elsewhere');
    config(['table-views.connection' => 'views']);
    runViewsMigration();

    DB::setDefaultConnection('elsewhere');

    $view = TableView::create(['user_id' => '1', 'name' => 'Pinned', 'resource' => 'res', 'state' => []]);

    expect($view->getConnectionName())->toBe('views')
        ->and(DB::connection('views')->table('table_views')->count())->toBe(1)
        ->and(DB::connection('testing')->table('table_views')->count())->toBe(0)
        ->and(TableView::query()->ownedBy('1')->count())->toBe(1);
});

it('saves and applies through the page on the configured connection', function () {
    $this->signIn();
    $this->seedOrders();

    sqliteConnections('views');
    config(['table-views.connection' => 'views']);
    runViewsMigration();

    $view = quickSave(Livewire::test(ListOrders::class)->filterTable('status', 'paid'), 'On views');

    expect($view->getConnectionName())->toBe('views')
        ->and(DB::connection('views')->table('table_views')->count())->toBe(1)
        ->and(DB::connection('testing')->table('table_views')->count())->toBe(0);

    Livewire::test(ListOrders::class)
        ->assertSee('On views')
        ->call('applyTableView', $view->id)
        ->assertSet('tableFilters.status.value', 'paid');
});

it('lets the plugin connection win over the config connection', function () {
    sqliteConnections('configured', 'plugin');
    config(['table-views.connection' => 'plugin']);
    runViewsMigration();
    config(['table-views.connection' => 'configured']);

    TableViewsPlugin::get()->connection('plugin');

    $view = TableView::create(['user_id' => '1', 'name' => 'Plugin', 'resource' => 'res', 'state' => []]);

    expect($view->getConnectionName())->toBe('plugin')
        ->and(DB::connection('plugin')->table('table_views')->count())->toBe(1);
});

it('falls back to the app default connection when nothing is configured', function () {
    expect((new TableView)->getConnectionName())->toBeNull()
        ->and((new TableView)->getConnection()->getName())->toBe('testing');
});
