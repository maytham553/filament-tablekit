<?php

namespace Elite\TableViews\Support;

use Elite\TableViews\TableViewsPlugin;
use Filament\Facades\Filament;

class TableViewsOptions
{
    public function __construct(protected ?TableViewsPlugin $plugin = null) {}

    public static function resolve(): self
    {
        return TableViewsPlugin::resolve();
    }

    public static function currentPlugin(): ?TableViewsPlugin
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel?->hasPlugin('table-views')) {
            return null;
        }

        /** @var TableViewsPlugin */
        return $panel->getPlugin('table-views');
    }

    public function favoritesBarEnabled(): bool
    {
        return $this->plugin?->isFavoritesBarEnabled() ?? (bool) config('table-views.favorites_bar.enabled', true);
    }

    public function isQuickSaveSlideOver(): bool
    {
        return $this->plugin?->isQuickSaveSlideOver() ?? (bool) config('table-views.quick_save.slide_over', false);
    }

    /** @return array<string> */
    public function colors(): array
    {
        return $this->plugin?->getColors() ?? array_values(config('table-views.colors', []));
    }

    /** @return array<string> */
    public function icons(): array
    {
        return $this->plugin?->getIcons() ?? array_values(config('table-views.icons', []));
    }

    public function persistActiveViewInSession(): bool
    {
        return $this->plugin?->shouldPersistActiveView() ?? (bool) config('table-views.persist_active_view_in_session', false);
    }

    public function userModel(): ?string
    {
        return $this->plugin?->getUserModel()
            ?? config('table-views.user.model')
            ?? config('auth.providers.'.config('auth.guards.'.config('auth.defaults.guard').'.provider').'.model');
    }

    public function tableName(): string
    {
        return (string) config('table-views.table', 'table_views');
    }

    public function connection(): ?string
    {
        return $this->plugin?->getConnection() ?? config('table-views.connection');
    }

    public function maxNameLength(): int
    {
        return min(100, max(1, (int) config('table-views.quick_save.max_name_length', 100)));
    }

    public function captureTab(): bool
    {
        return (bool) config('table-views.state.tab', false);
    }

    public function maxStateBytes(): int
    {
        return (int) config('table-views.state.max_bytes', 65536);
    }
}
