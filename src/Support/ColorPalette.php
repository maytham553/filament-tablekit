<?php

namespace TableKit\Support;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

/**
 * The Tailwind palettes the color picker offers on top of Filament's own names (primary, gray, success, warning,
 * danger, info). Only names nobody registered yet are added. Anything the app or a panel registers later is merged after
 * these, so it wins over them too.
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
        FilamentColor::register(array_diff_key(static::extras(), FilamentColor::getColors()));
    }
}
