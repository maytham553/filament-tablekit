<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrdersWithTabs extends ListOrders
{
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'paid' => Tab::make()->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'paid')),
        ];
    }
}
