<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Column action, summary, bulk action and record URL next to the columns that get reordered.
 */
class ListOrdersRich extends ListOrders
{
    public static array $peeked = [];

    public static array $marked = [];

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                TextColumn::make('reference')->sortable()->searchable()->action(function (Model $record): void {
                    static::$peeked[] = $record->reference;
                }),
                TextColumn::make('status')->sortable(),
                TextColumn::make('amount')->sortable()->summarize(Sum::make('sum')->label('Total')),
                TextColumn::make('notes')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (Model $record): string => '/orders/'.$record->getKey())
            ->bulkActions([
                BulkAction::make('mark')->action(function (Collection $records): void {
                    static::$marked = $records->pluck('reference')->all();
                }),
            ]);
    }
}
