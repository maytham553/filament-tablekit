<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersUnpersistedColumns extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)->persistColumnsInSession(false);
    }
}
