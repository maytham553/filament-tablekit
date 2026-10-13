<?php

namespace TableKit\Tests\Fixtures\Pages;

class ListOrdersDottedColumnPresets extends ListOrdersDottedColumn
{
    protected function getPresetTableViews(): array
    {
        return value(ListOrdersWithPresets::$presets) ?? [];
    }
}
