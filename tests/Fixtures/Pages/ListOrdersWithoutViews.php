<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Resources\Pages\ListRecords;
use TableKit\Tests\Fixtures\OrderResource;

class ListOrdersWithoutViews extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
