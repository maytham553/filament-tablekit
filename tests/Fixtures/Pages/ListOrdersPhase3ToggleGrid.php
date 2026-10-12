<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersPhase3ToggleGrid extends ListOrdersPhase3
{
    public function table(Table $table): Table
    {
        return parent::table($table)->columnToggleFormColumns(['default' => 1, 'md' => 2, 'lg' => 3]);
    }
}
