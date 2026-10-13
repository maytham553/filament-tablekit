<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListOrdersWithDefaults extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->filters([
                Filter::make('hide_cancelled')
                    ->label('Hide cancelled')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', 'cancelled')),
                Filter::make('big')
                    ->label('Big orders')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('amount', '>=', 500)),
            ])
            ->defaultSort('amount', 'desc')
            ->defaultGroup('status');
    }
}
