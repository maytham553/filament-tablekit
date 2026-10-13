<?php

namespace TableKit\Tests\Fixtures\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use TableKit\Concerns\HasTableKit;
use TableKit\Tests\Fixtures\Order;

class OrdersWidget extends TableWidget
{
    use HasTableKit;

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query())
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('status')->sortable(),
                TextColumn::make('amount')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                ]),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50]);
    }
}
