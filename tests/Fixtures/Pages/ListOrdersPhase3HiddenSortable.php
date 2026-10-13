<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A sortable column the user cannot see: it must never be sortable through the plugin.
 */
class ListOrdersPhase3HiddenSortable extends ListOrdersPhase3Presets
{
    public function table(Table $table): Table
    {
        return parent::table($table)->pushColumns([
            TextColumn::make('customer_id')->sortable()->hidden(),
        ]);
    }
}
