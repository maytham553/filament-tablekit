<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Elite\TableViews\Presets\PresetTableView;

class ListOrdersWithPublicPresets extends ListOrders
{
    public function getPresetTableViews(): array
    {
        return ['one' => PresetTableView::make()->label('One')->favorite()];
    }
}
