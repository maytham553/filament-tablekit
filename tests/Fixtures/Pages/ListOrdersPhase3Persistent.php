<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersPhase3Persistent extends ListOrdersPhase3
{
    public function table(Table $table): Table
    {
        return parent::table($table)->persistSortInSession();
    }
}
