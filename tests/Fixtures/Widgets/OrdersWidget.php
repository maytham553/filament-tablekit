<?php

namespace Elite\TableViews\Tests\Fixtures\Widgets;

use Elite\TableViews\Concerns\HasTableViews;
use Elite\TableViews\Tests\Fixtures\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OrdersWidget extends TableWidget
{
    use HasTableViews;

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
