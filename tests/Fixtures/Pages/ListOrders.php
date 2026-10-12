<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Elite\TableViews\Concerns\HasTableViews;
use Elite\TableViews\Tests\Fixtures\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    use HasTableViews;

    protected static string $resource = OrderResource::class;
}
