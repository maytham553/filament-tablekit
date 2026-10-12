<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

class ListOrdersWithPresetsAndDefaults extends ListOrdersWithDefaults
{
    protected function getPresetTableViews(): array
    {
        return value(ListOrdersWithPresets::$presets) ?? [];
    }
}
