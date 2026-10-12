<?php

namespace Elite\TableViews\Tests\Fixtures;

use Elite\TableViews\Tests\Fixtures\Pages\EditCustomer;
use Elite\TableViews\Tests\Fixtures\RelationManagers\OrdersRelationManager;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')]);
    }

    public static function getRelations(): array
    {
        return [OrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
