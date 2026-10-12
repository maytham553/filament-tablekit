<?php

namespace Elite\TableViews\Tests\Fixtures;

use Elite\TableViews\TableViewsPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([OrderResource::class, CustomerResource::class])
            ->plugin(TableViewsPlugin::make());
    }
}
