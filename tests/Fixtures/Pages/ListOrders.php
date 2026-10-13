<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Resources\Pages\ListRecords;
use TableKit\Concerns\HasTableKit;
use TableKit\Tests\Fixtures\OrderResource;

class ListOrders extends ListRecords
{
    use HasTableKit;

    protected static string $resource = OrderResource::class;
}
