<?php

use Filament\Support\Facades\FilamentIcon;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use TableKit\Models\TableView;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = $this->signIn();
});

const MANAGER_ICON_ALIASES = [
    'open', 'preset', 'user-view', 'favorite', 'unfavorite', 'default', 'remove-default', 'edit', 'delete', 'active',
];

it('registers a heroicon default for every manager alias', function () {
    foreach (MANAGER_ICON_ALIASES as $name) {
        expect(FilamentIcon::resolve("tablekit::manager.{$name}"))->toBeString()->toStartWith('heroicon-', $name);
    }

    expect(FilamentIcon::resolve('tablekit::reorder.handle'))->toBeString()->toStartWith('heroicon-')
        ->and(FilamentIcon::resolve('tablekit::manager.move-up'))->toBeNull()
        ->and(FilamentIcon::resolve('tablekit::sort.move-down'))->toBeNull()
        ->and(FilamentIcon::resolve('tablekit::columns.move-up'))->toBeNull();
});

it('registers aliases for the preset only icon keys', function () {
    foreach (['truck', 'calendar', 'shopping-cart', 'archive', 'document', 'bell', 'home', 'globe'] as $key) {
        expect(FilamentIcon::resolve("tablekit::icons.{$key}"))->toBeString()->toStartWith('heroicon-', $key);
    }

    expect(FilamentIcon::resolve('tablekit::icons.not-a-real-key'))->toBeNull();
});

it('never hard codes a heroicon in a view or outside the provider defaults', function () {
    foreach (array_merge(glob(__DIR__.'/../../resources/views/*.blade.php'), glob(__DIR__.'/../../resources/views/components/*.blade.php')) as $file) {
        expect(file_get_contents($file))->not->toContain('heroicon', basename($file));
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../src'));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getFilename() !== 'TableKitServiceProvider.php') {
            expect(file_get_contents($file->getPathname()))->not->toContain('heroicon', $file->getFilename());
        }
    }
});

it('renders remapped manager icons from the aliases', function () {
    $this->seedOrders();

    FilamentIcon::register([
        'tablekit::manager.edit' => new HtmlString('<svg data-test="remapped-edit"></svg>'),
        'tablekit::manager.open' => new HtmlString('<svg data-test="remapped-open"></svg>'),
        'tablekit::reorder.handle' => new HtmlString('<svg data-test="remapped-handle"></svg>'),
    ]);

    TableView::create(['user_type' => User::class, 'user_id' => $this->user->id, 'name' => 'Mine', 'resource' => ListOrders::class, 'state' => []]);
    TableView::create(['user_type' => User::class, 'user_id' => $this->user->id, 'name' => 'Mine two', 'resource' => ListOrders::class, 'state' => []]);

    Livewire::test(ListOrders::class)
        ->assertSeeHtml('data-test="remapped-open"')
        ->call('openTableViewsManager')
        ->assertSeeHtml('data-test="remapped-edit"')
        ->assertSeeHtml('data-test="remapped-handle"');
});
