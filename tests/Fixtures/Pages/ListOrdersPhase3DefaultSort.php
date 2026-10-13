<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersPhase3DefaultSort extends ListOrdersPhase3
{
    public function table(Table $table): Table
    {
        return parent::table($table)->defaultSort('amount', 'desc');
    }
}
