<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\Support\TableViewsOptions;
use Elite\TableViews\TableViewsPlugin;
use Elite\TableViews\TableViewsServiceProvider;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Icons\IconManager;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
});

function freshProviderDefaults(): void
{
    $provider = new TableViewsServiceProvider(app());
    $method = new ReflectionMethod($provider, 'registerIconDefaults');
    $method->invoke($provider);
}

it('registers heroicon defaults for every alias', function () {
    foreach (['table-views::favorites-bar.default', 'table-views::quick-save', 'table-views::icons.star', 'table-views::icons.chart-bar', 'table-views::icons.inbox'] as $alias) {
        expect(FilamentIcon::resolve($alias))->toBeInstanceOf(Heroicon::class);
    }

    foreach (config('table-views.icons') as $icon) {
        expect(FilamentIcon::resolve("table-views::icons.{$icon}"))->not->toBeNull($icon);
    }
});

it('does not overwrite an alias that is already registered', function () {
    app()->singleton(IconManager::class, fn () => new IconManager);
    FilamentIcon::clearResolvedInstances();

    FilamentIcon::register(['table-views::quick-save' => 'tabler-bookmark']);

    freshProviderDefaults();

    expect(FilamentIcon::resolve('table-views::quick-save'))->toBe('tabler-bookmark')
        ->and(FilamentIcon::resolve('table-views::icons.star'))->toBe(Heroicon::Star);
});

it('uses the resolved alias in the favorites bar', function () {
    $this->seedOrders();

    FilamentIcon::register([
        'table-views::favorites-bar.default' => new HtmlString('<svg data-test="remapped-default"></svg>'),
        'table-views::icons.star' => new HtmlString('<svg data-test="remapped-star"></svg>'),
    ]);

    storeView($this->user->id, [], ['name' => 'Starred', 'icon' => 'star']);

    Livewire::test(ListOrders::class)
        ->assertSeeHtml('data-test="remapped-default"')
        ->assertSeeHtml('data-test="remapped-star"');
});

it('never hard-codes a heroicon in the views', function () {
    $files = [...glob(__DIR__.'/../../resources/views/*.blade.php'), ...glob(__DIR__.'/../../resources/views/components/*.blade.php'), ...glob(__DIR__.'/../../src/*.php'), ...glob(__DIR__.'/../../src/*/*.php')];

    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toContain('heroicon-', basename($file));
    }
});

it('resolves options from config when the panel has no plugin', function () {
    Filament::setCurrentPanel(Filament::getPanel('team'));

    $options = TableViewsOptions::resolve();

    expect($options->colors())->toBe(config('table-views.colors'))
        ->and($options->favoritesBarEnabled())->toBeTrue()
        ->and($options->persistActiveViewInSession())->toBeFalse();
});

it('lets the plugin override every option', function () {
    TableViewsPlugin::get()
        ->favoritesBar(false)
        ->quickSaveSlideOver()
        ->colors(['danger'])
        ->icons(['star', 'heart'])
        ->userModel('App\\Models\\Admin')
        ->persistActiveViewInSession()
        ->connection('views');

    $options = TableViewsOptions::resolve();

    expect($options->favoritesBarEnabled())->toBeFalse()
        ->and($options->isQuickSaveSlideOver())->toBeTrue()
        ->and($options->colors())->toBe(['danger'])
        ->and($options->icons())->toBe(['star', 'heart'])
        ->and($options->userModel())->toBe('App\\Models\\Admin')
        ->and($options->persistActiveViewInSession())->toBeTrue()
        ->and($options->connection())->toBe('views');
});

it('falls back to the auth provider user model', function () {
    expect(TableViewsOptions::resolve()->userModel())->toBe(User::class);
});

it('is registered under its id', function () {
    expect(TableViewsPlugin::get()->getId())->toBe('table-views')
        ->and(Filament::getPanel('admin')->hasPlugin('table-views'))->toBeTrue();
});

it('publishes the migration under the documented tag', function () {
    $paths = ServiceProvider::pathsToPublish(TableViewsServiceProvider::class, 'table-views-migrations');

    expect($paths)->toHaveCount(2)
        ->and(array_key_first($paths))->toEndWith('create_table_views_table.php')
        ->and(array_values($paths)[0])->toContain('_create_table_views_table.php')
        ->and(array_keys($paths)[1])->toEndWith('create_table_view_preferences_table.php')
        ->and(array_values($paths)[1])->toContain('_create_table_view_preferences_table.php');
});

it('reads the table name from config only, like the migration', function () {
    expect((new TableView)->getTable())->toBe('table_views');

    config(['table-views.table' => 'custom_views']);

    expect((new TableView)->getTable())->toBe('custom_views')
        ->and(method_exists(TableViewsPlugin::class, 'tableName'))->toBeFalse()
        ->and(method_exists(TableViewsPlugin::class, 'userKey'))->toBeFalse()
        ->and(method_exists(TableViewsPlugin::class, 'userTable'))->toBeFalse();
});
