<?php

namespace TableKit\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use TableKit\Tests\Fixtures\Pages\EditCustomer;
use TableKit\Tests\Fixtures\RelationManagers\OrdersRelationManager;

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
