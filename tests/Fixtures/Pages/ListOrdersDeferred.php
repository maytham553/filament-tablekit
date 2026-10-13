<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersDeferred extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)->deferFilters();
    }
}
