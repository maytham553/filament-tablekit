<?php

namespace Elite\TableViews\Tests\Fixtures\Widgets;

use Elite\TableViews\Presets\PresetTableView;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersWidgetWithHeading extends OrdersWidget
{
    protected function getPresetTableViews(): array
    {
        return [
            'paid' => PresetTableView::make()
                ->label('Paid only')
                ->favorite()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid')),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->heading('Latest orders');
    }
}
