<?php

namespace TableKit\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use TableKit\TableKitPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([OrderResource::class, CustomerResource::class])
            ->plugin(TableKitPlugin::make());
    }
}
