<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Elite\TableViews\Tests\Fixtures\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrdersWithoutViews extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
