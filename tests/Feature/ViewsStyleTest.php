<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;
use Livewire\Livewire;

it('leaves layout to inline styles, because the precompiled Filament css has no utility classes of its own', function () {
    $files = [...glob(__DIR__.'/../../resources/views/*.blade.php'), ...glob(__DIR__.'/../../resources/views/components/*.blade.php')];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        preg_match_all('/\sclass="([^"]*)"/', file_get_contents($file), $matches);

        foreach ($matches[1] as $classes) {
            foreach (preg_split('/\s+/', trim($classes)) as $class) {
                if ($class === '' || str_contains($class, '{{') || str_contains($class, '@')) {
                    continue;
                }

                expect(str_starts_with($class, 'fi-'))->toBeTrue(basename($file).": [{$class}] is a utility class");
            }
        }
    }
});

it('uses the attributes of the sortable directive Filament 4 ships', function () {
    $user = $this->signIn();
    $this->seedOrders();

    foreach (['One', 'Two'] as $name) {
        storeView($user->id, [], ['name' => $name, 'is_favorite' => false, 'resource' => ListOrdersWithPresets::class]);
    }

    $html = Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager')->html();

    expect($html)->toContain('x-sortable')
        ->and($html)->toContain('x-on:end.stop="$event.oldIndex !== $event.newIndex')
        ->and(substr_count($html, 'x-sortable-item="'))->toBe(TableView::count())
        ->and(preg_match_all('/\sx-sortable-handle[\s>=\/]/', $html))->toBe(TableView::count())
        ->and($html)->toContain('x-on:keydown.arrow-up.prevent="moveItem($el, -1)"')
        ->and($html)->toContain('x-on:keydown.arrow-down.prevent="moveItem($el, 1)"');
});

it('shows the hovered manager row and keeps Arabic headings free of letter spacing', function () {
    $user = $this->signIn();
    $this->seedOrders(3);
    storeView($user->id, [], ['name' => 'One', 'resource' => ListOrdersWithPresets::class]);

    $html = Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager')->html();

    expect($html)->toContain('.fi-table-views-manager-row > div:hover')
        ->and($html)->toMatch('/\[dir="rtl"\] \.fi-table-views-manager-heading \{ letter-spacing: normal/')
        ->and($html)->toMatch('/<h4 class="fi-table-views-manager-heading" style="(?![^"]*letter-spacing)/');
});
