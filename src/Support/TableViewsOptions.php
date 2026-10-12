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

    public function preferencesTableName(): string
    {
        return (string) config('table-views.preferences_table', 'table_view_preferences');
    }

    public function favoritesBarDivider(): bool
    {
        return $this->plugin?->hasFavoritesBarDivider() ?? (bool) config('table-views.favorites_bar.divider', true);
    }

    public function viewManagerEnabled(): bool
    {
        return $this->plugin?->isViewManagerEnabled() ?? (bool) config('table-views.view_manager.enabled', true);
    }

    public function isViewManagerSlideOver(): bool
    {
        return $this->plugin?->isViewManagerSlideOver() ?? (bool) config('table-views.view_manager.slide_over', false);
    }

    /** @return 'start'|'end' */
    public function viewManagerPosition(): string
    {
        $position = $this->plugin?->getViewManagerPosition() ?? config('table-views.view_manager.position', 'end');

        return $position === 'start' ? 'start' : 'end';
    }

    public function viewManagerSearch(): ?bool
    {
        $search = config('table-views.view_manager.search');

        return $search === null ? null : (bool) $search;
    }

    public function presetViewsManageable(): bool
    {
        return $this->plugin?->arePresetViewsManageable() ?? (bool) config('table-views.preset_views.manageable', true);
    }

    /** @return 'before'|'after' */
    public function newPresetViewSortPosition(): string
    {
        $position = $this->plugin?->getNewPresetViewSortPosition() ?? config('table-views.preset_views.new_sort_position', 'after');

        return $position === 'before' ? 'before' : 'after';
    }

    public function presetViewLockIcon(): bool
    {
        return $this->plugin?->hasPresetViewLockIcon() ?? (bool) config('table-views.preset_views.lock_icon', true);
    }

    public function createFromPresetWithQuery(): bool
    {
        return $this->plugin?->shouldCreateFromPresetWithQuery() ?? (bool) config('table-views.preset_views.create_from_preset_with_query', false);
    }

    public function managedDefaultViews(): bool
    {
        return $this->plugin?->hasManagedDefaultViews() ?? (bool) config('table-views.managed_default_views', false);
    }

    public function multiSortEnabled(): bool
    {
        return $this->plugin?->isMultiSortEnabled() ?? (bool) config('table-views.multi_sort.enabled', true);
    }

    public function maxSorts(): int
    {
        $max = $this->plugin?->getMaxSorts() ?? (int) config('table-views.multi_sort.max', 5);

        return min(20, max(2, $max));
    }

    public function quickFiltersEnabled(): bool
    {
        return $this->plugin?->areQuickFiltersEnabled() ?? (bool) config('table-views.quick_filters.enabled', false);
    }

    /** How many selected values a multi-select chip shows before "& N more". Null = no shortening. */
    public function limitIndicatorLabels(): ?int
    {
        $limit = $this->plugin?->getIndicatorLabelLimit() ?? config('table-views.quick_filters.limit_labels', 2);

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    public function columnReorderingEnabled(): bool
    {
        return $this->plugin?->isColumnReorderingEnabled() ?? (bool) config('table-views.column_reordering.enabled', true);
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
