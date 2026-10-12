<?php

namespace Elite\TableViews\Tests\Fixtures\Widgets;

use Filament\Tables\Table;

class OrdersWidgetWithHeading extends OrdersWidget
{
    public function table(Table $table): Table
    {
        return parent::table($table)->heading('Latest orders');
    }
}
