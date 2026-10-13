<?php

namespace TableKit\Tests\Fixtures\RelationManagers;

use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;

class OrdersWithPresetsRelationManager extends OrdersRelationManager
{
    protected function getPresetTableViews(): array
    {
        return value(ListOrdersWithPresets::$presets) ?? [];
    }
}
