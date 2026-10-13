<?php

namespace Elite\TableViews\Support;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

/**
 * The Tailwind palettes the color picker offers on top of Filament's own names (primary, gray, success, warning,
 * danger, info). They are registered before the app boots, so anything the app or a panel registers under the same
 * name wins.
 */
class ColorPalette
{
    public const EXTRA = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo',
        'violet', 'purple', 'fuchsia', 'pink', 'rose', 'slate',
    ];

    /**
     * @return array<string, array<int|string, string>>
     */
    public static function extras(): array
    {
        $palettes = [];

        foreach (static::EXTRA as $name) {
            $palettes[$name] = constant(Color::class.'::'.ucfirst($name));
        }

        return $palettes;
    }

    public static function register(): void
    {
        FilamentColor::register(static::extras());
    }
}
