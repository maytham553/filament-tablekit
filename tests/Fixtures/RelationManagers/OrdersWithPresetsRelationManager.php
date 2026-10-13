<?php

namespace Elite\TableViews\Tests\Fixtures\RelationManagers;

use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersWithPresets;

class OrdersWithPresetsRelationManager extends OrdersRelationManager
{
    protected function getPresetTableViews(): array
    {
        return value(ListOrdersWithPresets::$presets) ?? [];
    }
}
