<?php

use Filament\Support\Colors\Color;
use Filament\Support\Colors\ColorManager;
use Filament\Support\Facades\FilamentColor;
use TableKit\Support\ColorPalette;

beforeEach(function () {
    app()->singleton(ColorManager::class, fn () => new ColorManager);
    FilamentColor::clearResolvedInstance(ColorManager::class);
});

it('registers the extra colors the app has not registered', function () {
    ColorPalette::register();

    $colors = FilamentColor::getColors();

    foreach (ColorPalette::EXTRA as $name) {
        expect($colors)->toHaveKey($name);
    }

    expect($colors['red'])->toBe(Color::Red);
});

it('keeps a color the app registered before the plugin', function () {
    $custom = Color::hex('#123456');

    FilamentColor::register(['red' => $custom]);

    ColorPalette::register();

    $colors = FilamentColor::getColors();

    expect($colors['red'])->toBe($custom)
        ->and($colors['blue'])->toBe(Color::Blue)
        ->and($colors['rose'])->toBe(Color::Rose);
});

it('keeps a color the app registers after the plugin', function () {
    $custom = Color::hex('#654321');

    ColorPalette::register();

    FilamentColor::register(['red' => $custom]);

    $colors = FilamentColor::getColors();

    expect($colors['red'])->toBe($custom)
        ->and($colors['blue'])->toBe(Color::Blue);
});

it('keeps a color the app registers with a closure before the plugin', function () {
    $custom = Color::hex('#0a0b0c');

    FilamentColor::register(fn (): array => ['teal' => $custom]);

    ColorPalette::register();

    expect(FilamentColor::getColors()['teal'])->toBe($custom);
});

it('does not touch the built in names', function () {
    ColorPalette::register();

    expect(FilamentColor::getColors())->toHaveKeys(['primary', 'gray', 'success', 'warning', 'danger', 'info'])
        ->and(FilamentColor::getColors()['primary'])->toBe(Color::Amber);
});
