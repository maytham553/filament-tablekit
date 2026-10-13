<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

class ListOrdersPhase3ColumnsPresets extends ListOrdersPhase3Columns
{
    protected function getPresetTableViews(): array
    {
        return value(ListOrdersPhase3Presets::$presets) ?? [];
    }
}
