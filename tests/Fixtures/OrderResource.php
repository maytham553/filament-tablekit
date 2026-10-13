<?php

namespace TableKit\Tests\Fixtures;

use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TableKit\Tests\Fixtures\Pages\ListOrders;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static bool $isScopedToTenant = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable(isIndividual: true)->sortable(),
                TextColumn::make('customer_name')->searchable()->sortable(),
                TextColumn::make('status')->sortable(),
                TextColumn::make('amount')->sortable(),
                TextColumn::make('notes')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ordered_at')->date()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'shipped' => 'Shipped',
                    'cancelled' => 'Cancelled',
                ]),
                Filter::make('big')
                    ->label('Big orders')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('amount', '>=', 500)),
                Filter::make('ordered')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('ordered_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('ordered_at', '<=', $date))),
            ])
            ->groups([
                Group::make('status'),
                Group::make('customer_name'),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
