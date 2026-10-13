<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Resources\Pages\ListRecords;
use TableKit\Concerns\HasTableKit;
use TableKit\Tests\Fixtures\TeamOrderResource;

class ListTeamOrders extends ListRecords
{
    use HasTableKit;

    protected static string $resource = TeamOrderResource::class;

    protected function getPresetTableViews(): array
    {
        return value(ListOrdersWithPresets::$presets) ?? [];
    }
}
