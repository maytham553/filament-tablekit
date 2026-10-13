<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListOrdersOneSortable extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)->columns([
            TextColumn::make('reference')->sortable(),
            TextColumn::make('status'),
            TextColumn::make('amount')->toggleable(),
        ]);
    }
}
