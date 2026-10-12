<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListOrdersColumnGroups extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)->columns([
            TextColumn::make('reference')->sortable(),
            ColumnGroup::make('Money', [
                TextColumn::make('amount')->sortable()->toggleable(),
                TextColumn::make('status')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ]),
            TextColumn::make('notes')->toggleable(),
        ]);
    }
}
