<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersPhase3Columns extends ListOrdersPhase3
{
    public function table(Table $table): Table
    {
        return parent::table($table)->reorderableColumns();
    }
}
