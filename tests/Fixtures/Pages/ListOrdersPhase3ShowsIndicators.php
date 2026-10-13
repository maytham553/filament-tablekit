<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Tables\Table;

class ListOrdersPhase3ShowsIndicators extends ListOrdersPhase3
{
    public static bool $hide = false;

    public function table(Table $table): Table
    {
        return parent::table($table)->hiddenFilterIndicators(static::$hide);
    }
}
