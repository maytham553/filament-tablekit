<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A form filter with no indicators whose field has a default value.
 */
class ListOrdersPhase3DefaultedFilter extends ListOrdersPhase3Indicators
{
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table->filters([
            ...array_values($table->getFilters(withHidden: true)),
            Filter::make('period')
                ->schema([Select::make('p')->options(['all' => 'All', 'week' => 'Week'])->default('all')])
                ->query(fn (Builder $query): Builder => $query),
        ]);
    }
}
