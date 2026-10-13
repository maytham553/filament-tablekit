<?php

namespace TableKit\Tests\Fixtures\Pages;

use Closure;

class ListOrdersPhase3Presets extends ListOrdersPhase3
{
    /** @var Closure|array<string, mixed>|null */
    public static Closure|array|null $presets = null;

    protected function getPresetTableViews(): array
    {
        return value(static::$presets) ?? [];
    }
}
