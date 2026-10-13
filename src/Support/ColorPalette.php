<?php

namespace TableKit\Support;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

/**
 * The Tailwind palettes the color picker offers on top of Filament's own names (primary, gray, success, warning,
 * danger, info). They go to the front of Filament's color list, so whatever the app or a panel registers under the same
 * name wins, whether it registers before or after the plugin. The list is edited, not read with getColors(), because
 * that call freezes Filament's color cache and would drop every later registration.
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
        $manager = FilamentColor::getFacadeRoot();
        $extras = static::extras();

        if (! property_exists($manager, 'colors')) {
            FilamentColor::register($extras);

            return;
        }

        (function () use ($extras): void {
            array_unshift($this->colors, $extras);
        })->call($manager);
    }
}
