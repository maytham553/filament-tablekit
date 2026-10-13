<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Elite\TableViews\Concerns\HasTableViews;
use Elite\TableViews\Tests\Fixtures\TeamOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListTeamOrders extends ListRecords
{
    use HasTableViews;

    protected static string $resource = TeamOrderResource::class;

    protected function getPresetTableViews(): array
    {
        return value(ListOrdersWithPresets::$presets) ?? [];
    }
}
