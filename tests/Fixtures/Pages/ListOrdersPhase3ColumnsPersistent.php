<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersPhase3ColumnsPersistent extends ListOrdersPhase3Columns
{
    public function table(Table $table): Table
    {
        return parent::table($table)->persistSortInSession();
    }
}
