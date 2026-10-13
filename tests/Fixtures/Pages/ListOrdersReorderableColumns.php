<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListOrdersReorderableColumns extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                TextColumn::make('reference')->label('Reference')->sortable(),
                TextColumn::make('status')->label('Status')->sortable()->toggleable(),
                TextColumn::make('amount')->label('Amount')->sortable()->toggleable(),
                TextColumn::make('notes')->label('Notes')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->reorderableColumns();
    }
}
