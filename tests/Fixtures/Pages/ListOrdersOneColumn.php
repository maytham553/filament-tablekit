<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListOrdersOneColumn extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)->columns([
            TextColumn::make('reference')->sortable()->toggleable(),
        ]);
    }
}
