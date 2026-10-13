<?php

namespace TableKit\Tests\Fixtures\Pages;

class ListOrdersReorderableColumnsPresets extends ListOrdersReorderableColumns
{
    protected function getPresetTableViews(): array
    {
        return value(ListOrdersWithPresets::$presets) ?? [];
    }
}
