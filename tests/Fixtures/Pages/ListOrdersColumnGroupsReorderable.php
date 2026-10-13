<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListOrdersColumnGroupsReorderable extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                TextColumn::make('reference')->label('Reference')->sortable(),
                ColumnGroup::make('Money', [
                    TextColumn::make('amount')->label('Amount')->sortable()->toggleable(),
                    TextColumn::make('status')->label('Status')->sortable()->toggleable(isToggledHiddenByDefault: true),
                ]),
                TextColumn::make('notes')->label('Notes')->toggleable(),
            ])
            ->reorderableColumns();
    }
}
