<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ListOrdersHiddenDefaultGroup extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->groups([Group::make('customer_name')])
            ->defaultGroup('status');
    }
}
