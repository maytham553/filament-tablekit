<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Adds a preloaded relationship filter (a favorite, so it always has a chip).
 */
class ListOrdersPhase3Preload extends ListOrdersPhase3
{
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table->filters([
            ...array_values($table->getFilters(withHidden: true)),
            SelectFilter::make('team_id')->label('Team')->relationship('team', 'name')->preload()->favorite(),
        ]);
    }
}
