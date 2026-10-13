<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A filter with one removable and one locked indicator, and a filter that has a form but no indicators at all.
 */
class ListOrdersPhase3Indicators extends ListOrdersPhase3
{
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table->filters([
            ...array_values($table->getFilters(withHidden: true)),
            Filter::make('mixed')
                ->form([TextInput::make('a'), TextInput::make('b')])
                ->query(fn (Builder $query): Builder => $query)
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['a'] ?? null) {
                        $indicators[] = Indicator::make('A='.$data['a'])->removeField('a');
                    }

                    if ($data['b'] ?? null) {
                        $indicators[] = Indicator::make('B='.$data['b'])->removeField('b')->removable(false);
                    }

                    return $indicators;
                }),
            Filter::make('noind')
                ->label('Reference contains')
                ->form([TextInput::make('q')])
                ->query(fn (Builder $query, array $data): Builder => $query->when($data['q'] ?? null, fn (Builder $q, $term) => $q->where('reference', 'like', "%{$term}%"))),
        ]);
    }
}
