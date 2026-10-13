<?php

namespace TableKit\Tests\Fixtures\Pages;

use TableKit\Presets\PresetTableView;

class ListOrdersWithPublicPresets extends ListOrders
{
    public function getPresetTableViews(): array
    {
        return ['one' => PresetTableView::make()->label('One')->favorite()];
    }
}
