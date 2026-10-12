<?php

namespace Elite\TableViews\Tests\Fixtures\Widgets;

class SharedKeyWidget extends OrdersWidget
{
    protected function getTableViewsResourceKey(): string
    {
        return 'shared-orders';
    }
}
