<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersPhase3PersistentPresets extends ListOrdersPhase3Presets
{
    public function table(Table $table): Table
    {
        return parent::table($table)->persistSortInSession();
    }
}
