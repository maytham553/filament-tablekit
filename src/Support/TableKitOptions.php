<?php

namespace TableKit\Support;

use Closure;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentIcon;
use TableKit\TableKitPlugin;

class TableKitOptions
{
    public function __construct(protected ?TableKitPlugin $plugin = null) {}

    public static function resolve(): self
    {
        return TableKitPlugin::resolve();
    }

    public static function currentPlugin(): ?TableKitPlugin
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel?->hasPlugin('tablekit')) {
            return null;
        }

        /** @var TableKitPlugin */
        return $panel->getPlugin('tablekit');
    }

    public function favoritesBarEnabled(): bool
    {
        return $this->plugin?->isFavoritesBarEnabled() ?? (bool) config('tablekit.favorites_bar.enabled', true);
    }

    public function isQuickSaveSlideOver(): bool
    {
        return $this->plugin?->isQuickSaveSlideOver() ?? (bool) config('tablekit.quick_save.slide_over', false);
    }

    /**
     * The colors the picker offers and validation accepts: the configured list, minus names Filament has no color for.
     *
     * @return array<string>
     */
    public function colors(): array
    {
        $known = FilamentColor::getColors();

        return $this->onlyStrings($this->plugin?->getColors() ?? config('tablekit.colors', []), fn (string $color): bool => array_key_exists($color, $known));
    }

    /**
     * The icon keys the picker offers and validation accepts: the configured list, minus keys without an alias.
     *
     * @return array<string>
     */
    public function icons(): array
    {
        return $this->onlyStrings($this->plugin?->getIcons() ?? config('tablekit.icons', []), fn (string $icon): bool => FilamentIcon::resolve("tablekit::icons.{$icon}") !== null);
    }

    /**
     * @return array<string>
     */
    protected function onlyStrings(mixed $values, Closure $keep): array
    {
        return array_values(array_unique(array_filter(
            is_array($values) ? $values : [],
            fn (mixed $value): bool => is_string($value) && $value !== '' && $keep($value),
        )));
    }

    public function persistActiveViewInSession(): bool
    {
        return $this->plugin?->shouldPersistActiveView() ?? (bool) config('tablekit.persist_active_view_in_session', false);
    }

    public function userModel(): ?string
    {
        return $this->plugin?->getUserModel()
            ?? config('tablekit.user.model')
            ?? config('auth.providers.'.config('auth.guards.'.config('auth.defaults.guard').'.provider').'.model');
    }

    public function tableName(): string
    {
        return (string) config('tablekit.table', 'tablekit_views');
    }

    public function preferencesTableName(): string
    {
        return (string) config('tablekit.preferences_table', 'tablekit_preferences');
    }

    public function favoritesBarDivider(): bool
    {
        return $this->plugin?->hasFavoritesBarDivider() ?? (bool) config('tablekit.favorites_bar.divider', true);
    }

    public function viewManagerEnabled(): bool
    {
        return $this->plugin?->isViewManagerEnabled() ?? (bool) config('tablekit.view_manager.enabled', true);
    }

    public function isViewManagerSlideOver(): bool
    {
        return $this->plugin?->isViewManagerSlideOver() ?? (bool) config('tablekit.view_manager.slide_over', false);
    }

    /** @return 'start'|'end' */
    public function viewManagerPosition(): string
    {
        $position = $this->plugin?->getViewManagerPosition() ?? config('tablekit.view_manager.position', 'end');

        return $position === 'start' ? 'start' : 'end';
    }

    public function viewManagerSearch(): ?bool
    {
        $search = config('tablekit.view_manager.search');

        return $search === null ? null : (bool) $search;
    }

    public function presetViewsManageable(): bool
    {
        return $this->plugin?->arePresetViewsManageable() ?? (bool) config('tablekit.preset_views.manageable', true);
    }

    /** @return 'before'|'after' */
    public function newPresetViewSortPosition(): string
    {
        $position = $this->plugin?->getNewPresetViewSortPosition() ?? config('tablekit.preset_views.new_sort_position', 'after');

        return $position === 'before' ? 'before' : 'after';
    }

    public function presetViewLockIcon(): bool
    {
        return $this->plugin?->hasPresetViewLockIcon() ?? (bool) config('tablekit.preset_views.lock_icon', true);
    }

    public function createFromPresetWithQuery(): bool
    {
        return $this->plugin?->shouldCreateFromPresetWithQuery() ?? (bool) config('tablekit.preset_views.create_from_preset_with_query', false);
    }

    public function managedDefaultViews(): bool
    {
        return $this->plugin?->hasManagedDefaultViews() ?? (bool) config('tablekit.managed_default_views', false);
    }

    public function multiSortEnabled(): bool
    {
        return $this->plugin?->isMultiSortEnabled() ?? (bool) config('tablekit.multi_sort.enabled', true);
    }

    public function maxSorts(): int
    {
        $max = $this->plugin?->getMaxSorts() ?? (int) config('tablekit.multi_sort.max', 5);

        return min(20, max(2, $max));
    }

    public function quickFiltersEnabled(): bool
    {
        return $this->plugin?->areQuickFiltersEnabled() ?? (bool) config('tablekit.quick_filters.enabled', false);
    }

    /** How many selected values a multi-select chip shows before "& N more". Null = no shortening. */
    public function limitIndicatorLabels(): ?int
    {
        $limit = $this->plugin?->getIndicatorLabelLimit() ?? config('tablekit.quick_filters.limit_labels', 2);

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    public function columnReorderingEnabled(): bool
    {
        return $this->plugin?->isColumnReorderingEnabled() ?? (bool) config('tablekit.column_reordering.enabled', true);
    }

    public function connection(): ?string
    {
        return $this->plugin?->getConnection() ?? config('tablekit.connection');
    }

    public function maxNameLength(): int
    {
        return min(100, max(1, (int) config('tablekit.quick_save.max_name_length', 100)));
    }

    public function maxViewsPerTable(): int
    {
        return max(1, $this->plugin?->getMaxViewsPerTable() ?? (int) config('tablekit.max_views_per_table', 100));
    }

    public function captureTab(): bool
    {
        return (bool) config('tablekit.state.tab', false);
    }

    public function maxStateBytes(): int
    {
        return (int) config('tablekit.state.max_bytes', 65536);
    }
}
