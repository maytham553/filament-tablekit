<?php

use Filament\Support\Colors\Color;
use Filament\Support\Colors\ColorManager;
use Filament\Support\Facades\FilamentColor;
use TableKit\Support\ColorPalette;

beforeEach(function () {
    FilamentColor::swap(new ColorManager);
});

function processed(array $palette): array
{
    return array_map(fn (string $shade): string => Color::convertToOklch($shade), $palette);
}

it('keeps a color the app registered before the plugin registered its palette', function () {
    FilamentColor::register(['red' => Color::Pink]);

    ColorPalette::register();

    expect(FilamentColor::getColors()['red'])->toBe(processed(Color::Pink));
});

it('keeps a color the app registers after the plugin registered its palette', function () {
    ColorPalette::register();

    FilamentColor::register(['red' => Color::Pink]);

    expect(FilamentColor::getColors()['red'])->toBe(processed(Color::Pink));
});

it('keeps an app color given as a closure or a single hex value', function () {
    FilamentColor::register(fn (): array => ['blue' => Color::Orange]);
    ColorPalette::register();
    FilamentColor::register(['green' => '#ff0000']);

    $colors = FilamentColor::getColors();

    expect($colors['blue'])->toBe(processed(Color::Orange))
        ->and($colors['green'])->toBe(Color::generatePalette('#ff0000'));
});

it('still offers the extra colors the app did not register', function () {
    FilamentColor::register(['red' => Color::Pink]);

    ColorPalette::register();

    $colors = FilamentColor::getColors();

    foreach (array_diff(ColorPalette::EXTRA, ['red']) as $name) {
        expect($colors)->toHaveKey($name);
    }

    expect($colors['rose'])->toBe(processed(Color::Rose))
        ->and($colors['red'])->not->toBe(processed(Color::Red));
});

it('does not read the colors while registering, so later registrations are not dropped', function () {
    ColorPalette::register();

    FilamentColor::register(['brand' => Color::Teal]);

    expect(FilamentColor::getColors())->toHaveKey('brand');
});
