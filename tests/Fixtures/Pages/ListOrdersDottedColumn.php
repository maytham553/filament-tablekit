<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListOrdersDottedColumn extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)->columns([
            TextColumn::make('reference')->sortable(),
            TextColumn::make('team.name')->label('Team')->toggleable(),
            TextColumn::make('notes')->toggleable(isToggledHiddenByDefault: true),
        ]);
    }
}
