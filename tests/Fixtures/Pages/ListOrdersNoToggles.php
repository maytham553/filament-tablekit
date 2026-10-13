<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListOrdersNoToggles extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)->columns([
            TextColumn::make('reference')->sortable()->searchable(),
            TextColumn::make('status')->sortable(),
            TextColumn::make('amount')->sortable(),
        ]);
    }
}
