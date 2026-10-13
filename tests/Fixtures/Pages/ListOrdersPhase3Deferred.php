<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersPhase3Deferred extends ListOrdersPhase3
{
    public function table(Table $table): Table
    {
        return parent::table($table)->deferFilters();
    }
}
