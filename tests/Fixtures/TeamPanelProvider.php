<?php

namespace Elite\TableViews\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;

class TeamPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('team')
            ->path('team')
            ->tenant(Team::class)
            ->resources([OrderResource::class, TeamOrderResource::class]);
    }
}
