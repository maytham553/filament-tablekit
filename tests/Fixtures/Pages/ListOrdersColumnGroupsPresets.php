<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

class ListOrdersColumnGroupsPresets extends ListOrdersColumnGroups
{
    protected function getPresetTableViews(): array
    {
        return value(ListOrdersWithPresets::$presets) ?? [];
    }
}
