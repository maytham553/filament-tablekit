<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Closure;

class ListOrdersWithPresets extends ListOrders
{
    /** @var Closure|array<string, mixed>|null */
    public static Closure|array|null $presets = null;

    protected function getPresetTableViews(): array
    {
        return value(static::$presets) ?? [];
    }
}
