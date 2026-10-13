<?php

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\IconManager;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Support\TableKitOptions;
use TableKit\TableKitPlugin;
use TableKit\TableKitServiceProvider;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = $this->signIn();
});

function freshProviderDefaults(): void
{
    $provider = new TableKitServiceProvider(app());
    $method = new ReflectionMethod($provider, 'registerIconDefaults');
    $method->invoke($provider);
}

it('registers heroicon defaults for every alias', function () {
    foreach (['tablekit::favorites-bar.default', 'tablekit::quick-save', 'tablekit::icons.star', 'tablekit::icons.chart-bar', 'tablekit::icons.inbox'] as $alias) {
        expect(FilamentIcon::resolve($alias))->toBeString()->toStartWith('heroicon-');
    }

    foreach (config('tablekit.icons') as $icon) {
        expect(FilamentIcon::resolve("tablekit::icons.{$icon}"))->not->toBeNull($icon);
    }
});

it('does not overwrite an alias that is already registered', function () {
    app()->singleton(IconManager::class, fn () => new IconManager);
    FilamentIcon::clearResolvedInstances();

    FilamentIcon::register(['tablekit::quick-save' => 'tabler-bookmark']);

    freshProviderDefaults();

    expect(FilamentIcon::resolve('tablekit::quick-save'))->toBe('tabler-bookmark')
        ->and(FilamentIcon::resolve('tablekit::icons.star'))->toBe('heroicon-m-star');
});

it('uses the resolved alias in the favorites bar', function () {
    $this->seedOrders();

    FilamentIcon::register([
        'tablekit::favorites-bar.default' => new HtmlString('<svg data-test="remapped-default"></svg>'),
        'tablekit::icons.star' => new HtmlString('<svg data-test="remapped-star"></svg>'),
    ]);

    storeView($this->user->id, [], ['name' => 'Starred', 'icon' => 'star']);

    Livewire::test(ListOrders::class)
        ->assertSeeHtml('data-test="remapped-default"')
        ->assertSeeHtml('data-test="remapped-star"');
});

it('never hard-codes a heroicon in the views', function () {
    $view = file_get_contents(__DIR__.'/../../resources/views/favorites-bar.blade.php');

    expect($view)->not->toContain('heroicon');
});

it('resolves options from config when the panel has no plugin', function () {
    Filament::setCurrentPanel(Filament::getPanel('team'));

    $options = TableKitOptions::resolve();

    expect($options->colors())->toBe(config('tablekit.colors'))
        ->and($options->favoritesBarEnabled())->toBeTrue()
        ->and($options->persistActiveViewInSession())->toBeFalse();
});

it('lets the plugin override every option', function () {
    TableKitPlugin::get()
        ->favoritesBar(false)
        ->quickSaveSlideOver()
        ->colors(['danger'])
        ->icons(['star', 'heart'])
        ->userModel('App\\Models\\Admin')
        ->persistActiveViewInSession()
        ->connection('views');

    $options = TableKitOptions::resolve();

    expect($options->favoritesBarEnabled())->toBeFalse()
        ->and($options->isQuickSaveSlideOver())->toBeTrue()
        ->and($options->colors())->toBe(['danger'])
        ->and($options->icons())->toBe(['star', 'heart'])
        ->and($options->userModel())->toBe('App\\Models\\Admin')
        ->and($options->persistActiveViewInSession())->toBeTrue()
        ->and($options->connection())->toBe('views');
});

it('falls back to the auth provider user model', function () {
    expect(TableKitOptions::resolve()->userModel())->toBe(User::class);
});

it('is registered under its id', function () {
    expect(TableKitPlugin::get()->getId())->toBe('tablekit')
        ->and(Filament::getPanel('admin')->hasPlugin('tablekit'))->toBeTrue();
});

it('publishes the migration under the documented tag', function () {
    $paths = ServiceProvider::pathsToPublish(TableKitServiceProvider::class, 'tablekit-migrations');

    expect($paths)->toHaveCount(2)
        ->and(array_key_first($paths))->toEndWith('create_tablekit_views_table.php')
        ->and(array_values($paths)[0])->toContain('_create_tablekit_views_table.php')
        ->and(array_keys($paths)[1])->toEndWith('create_tablekit_preferences_table.php')
        ->and(array_values($paths)[1])->toContain('_create_tablekit_preferences_table.php');
});

it('reads the table name from config only, like the migration', function () {
    expect((new TableView)->getTable())->toBe('tablekit_views');

    config(['tablekit.table' => 'custom_views']);

    expect((new TableView)->getTable())->toBe('custom_views')
        ->and(method_exists(TableKitPlugin::class, 'tableName'))->toBeFalse()
        ->and(method_exists(TableKitPlugin::class, 'userKey'))->toBeFalse()
        ->and(method_exists(TableKitPlugin::class, 'userTable'))->toBeFalse();
});
