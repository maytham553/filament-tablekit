<?php

namespace Elite\TableViews\Tests\Fixtures;

use Elite\TableViews\Tests\Fixtures\Pages\ListTeamOrders;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Same table as OrderResource, but scoped to the current tenant (whereBelongsTo on orders.team_id).
 */
class TeamOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'team-orders';

    public static function table(Table $table): Table
    {
        return OrderResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamOrders::route('/'),
        ];
    }
}
