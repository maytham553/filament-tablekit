<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListOrdersSplit extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)->columns([
            Split::make([
                TextColumn::make('reference')->sortable(),
                TextColumn::make('amount')->sortable(),
            ]),
        ]);
    }
}
