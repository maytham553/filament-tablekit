<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Columns with a custom sort closure and a relationship, toggles, a favorite filter, a multi-select filter and a multi-field filter.
 */
class ListOrdersPhase3 extends ListOrders
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                TextColumn::make('reference')->sortable(),
                TextColumn::make('customer_name')->sortable(),
                TextColumn::make('status')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("case status when 'paid' then 1 when 'shipped' then 2 when 'pending' then 3 else 4 end {$direction}")),
                TextColumn::make('amount')->sortable(),
                TextColumn::make('internal')->state('secret')->hidden(),
                TextColumn::make('team.name')->label('Team')->sortable()->toggleable(),
                TextColumn::make('notes')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ordered_at')->date()->toggleable(),
                TextColumn::make('plain')->state('x'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'shipped' => 'Shipped',
                    'cancelled' => 'Cancelled',
                ])->favorite(),
                SelectFilter::make('multi')
                    ->label('Statuses')
                    ->attribute('status')
                    ->multiple()
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'shipped' => 'Shipped',
                        'cancelled' => 'Cancelled',
                    ]),
                Filter::make('big')
                    ->label('Big orders')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('amount', '>=', 500)),
                Filter::make('secret')
                    ->toggle()
                    ->hidden()
                    ->query(fn (Builder $query): Builder => $query->where('amount', '>=', 900)),
                Filter::make('ordered')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('ordered_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('ordered_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From '.$data['from'])->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Until '.$data['until'])->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->groups([
                Group::make('status'),
            ])
            ->deferFilters(false);
    }
}
