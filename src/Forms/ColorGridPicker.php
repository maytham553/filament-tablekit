<?php

namespace Elite\TableViews\Forms;

use Closure;

use function Filament\Support\get_color_css_variables;

class ColorGridPicker extends GridPicker
{
    /**
     * @param  array<int, string>|Closure  $colors
     */
    public function colors(array|Closure $colors): static
    {
        return $this->keys($colors);
    }

    public function getKind(): string
    {
        return 'color';
    }

    public function getItems(): array
    {
        return array_map(fn (string $color): array => [
            'value' => $color,
            'label' => static::labelFor($color),
            'search' => mb_strtolower($color),
            'style' => get_color_css_variables($color, shades: [500]),
            'icon' => null,
            'none' => false,
        ], $this->getKeys());
    }

    public static function labelFor(string $color): string
    {
        $key = "table-views::colors.{$color}";
        $label = __($key);

        return $label === $key ? ucfirst($color) : $label;
    }
}
