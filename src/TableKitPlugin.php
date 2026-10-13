<?php

namespace TableKit;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use TableKit\Support\TableKitOptions;

class TableKitPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool|Closure|null $favoritesBar = null;

    protected bool|Closure|null $quickSaveSlideOver = null;

    /** @var array<string>|null */
    protected ?array $colors = null;

    /** @var array<string>|null */
    protected ?array $icons = null;

    protected ?string $userModel = null;

    protected ?bool $persistActiveView = null;

    protected ?string $connection = null;

    protected bool|Closure|null $favoritesBarDivider = null;

    protected bool|Closure|null $viewManager = null;

    protected bool|Closure|null $viewManagerSlideOver = null;

    protected ?string $viewManagerPosition = null;

    protected bool|Closure|null $presetViewsManageable = null;

    protected ?string $newPresetViewSortPosition = null;

    protected bool|Closure|null $presetViewLockIcon = null;

    protected bool|Closure|null $createFromPresetWithQuery = null;

    protected bool|Closure|null $managedDefaultViews = null;

    protected bool|Closure|null $multiSort = null;

    protected ?int $maxSorts = null;

    protected bool|Closure|null $quickFilters = null;

    protected ?int $indicatorLabelLimit = null;

    protected bool|Closure|null $columnReordering = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament('tablekit');
    }

    public static function resolve(): TableKitOptions
    {
        return new TableKitOptions(TableKitOptions::currentPlugin());
    }

    public function getId(): string
    {
        return 'tablekit';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    public function favoritesBar(bool|Closure $enabled = true): static
    {
        $this->favoritesBar = $enabled;

        return $this;
    }

    public function quickSaveSlideOver(bool|Closure $slideOver = true): static
    {
        $this->quickSaveSlideOver = $slideOver;

        return $this;
    }

    /** @param  array<string>  $colors */
    public function colors(array $colors): static
    {
        $this->colors = array_values($colors);

        return $this;
    }

    /** @param  array<string>  $keys */
    public function icons(array $keys): static
    {
        $this->icons = array_values($keys);

        return $this;
    }

    public function userModel(string $class): static
    {
        $this->userModel = $class;

        return $this;
    }

    public function persistActiveViewInSession(bool $condition = true): static
    {
        $this->persistActiveView = $condition;

        return $this;
    }

    /**
     * The database connection that holds the views table for this panel. Wins over config('tablekit.connection').
     */
    public function connection(?string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function favoritesBarDivider(bool|Closure $condition = true): static
    {
        $this->favoritesBarDivider = $condition;

        return $this;
    }

    /**
     * @param  'start'|'end'|null  $position
     */
    public function viewManager(bool|Closure $enabled = true, bool|Closure|null $slideOver = null, ?string $position = null): static
    {
        $this->viewManager = $enabled;
        $this->viewManagerSlideOver = $slideOver;
        $this->viewManagerPosition = $position;

        return $this;
    }

    public function presetViewsManageable(bool|Closure $condition = true): static
    {
        $this->presetViewsManageable = $condition;

        return $this;
    }

    /**
     * @param  'before'|'after'  $position
     */
    public function newPresetViewSortPosition(string $position): static
    {
        $this->newPresetViewSortPosition = $position;

        return $this;
    }

    public function presetViewLockIcon(bool|Closure $condition = true): static
    {
        $this->presetViewLockIcon = $condition;

        return $this;
    }

    public function createFromPresetWithQuery(bool|Closure $condition = true): static
    {
        $this->createFromPresetWithQuery = $condition;

        return $this;
    }

    public function managedDefaultViews(bool|Closure $condition = true): static
    {
        $this->managedDefaultViews = $condition;

        return $this;
    }

    /**
     * Sort by several columns at once (a Sort button next to the grouping selector). A header click still replaces it with that single sort.
     */
    public function multiSort(bool|Closure $enabled = true, ?int $max = null): static
    {
        $this->multiSort = $enabled;
        $this->maxSorts = $max;

        return $this;
    }

    /**
     * Replaces Filament's filter indicator bar with clickable chips (a popover with the filter's fields) and keeps favorite filters visible.
     */
    public function quickFilters(bool|Closure $enabled = true): static
    {
        $this->quickFilters = $enabled;

        return $this;
    }

    /**
     * Shorten a multi-select chip to "A, B & 3 more" after this many values. 0 or null = show all.
     */
    public function limitIndicatorLabels(?int $count = 2): static
    {
        $this->indicatorLabelLimit = $count ?? 0;

        return $this;
    }

    /**
     * Save and apply the column order (made in Filament's column manager) in views and presets.
     */
    public function columnReordering(bool|Closure $enabled = true): static
    {
        $this->columnReordering = $enabled;

        return $this;
    }

    public function isMultiSortEnabled(): ?bool
    {
        return $this->evaluateNullableBool($this->multiSort);
    }

    public function getMaxSorts(): ?int
    {
        return $this->maxSorts;
    }

    public function areQuickFiltersEnabled(): ?bool
    {
        return $this->evaluateNullableBool($this->quickFilters);
    }

    public function getIndicatorLabelLimit(): ?int
    {
        return $this->indicatorLabelLimit;
    }

    public function isColumnReorderingEnabled(): ?bool
    {
        return $this->evaluateNullableBool($this->columnReordering);
    }

    public function isFavoritesBarEnabled(): ?bool
    {
        return $this->favoritesBar === null ? null : (bool) $this->evaluate($this->favoritesBar);
    }

    public function isQuickSaveSlideOver(): ?bool
    {
        return $this->quickSaveSlideOver === null ? null : (bool) $this->evaluate($this->quickSaveSlideOver);
    }

    /** @return array<string>|null */
    public function getColors(): ?array
    {
        return $this->colors;
    }

    /** @return array<string>|null */
    public function getIcons(): ?array
    {
        return $this->icons;
    }

    public function getUserModel(): ?string
    {
        return $this->userModel;
    }

    public function shouldPersistActiveView(): ?bool
    {
        return $this->persistActiveView;
    }

    public function getConnection(): ?string
    {
        return $this->connection;
    }

    public function hasFavoritesBarDivider(): ?bool
    {
        return $this->evaluateNullableBool($this->favoritesBarDivider);
    }

    public function isViewManagerEnabled(): ?bool
    {
        return $this->evaluateNullableBool($this->viewManager);
    }

    public function isViewManagerSlideOver(): ?bool
    {
        return $this->evaluateNullableBool($this->viewManagerSlideOver);
    }

    public function getViewManagerPosition(): ?string
    {
        return $this->viewManagerPosition;
    }

    public function arePresetViewsManageable(): ?bool
    {
        return $this->evaluateNullableBool($this->presetViewsManageable);
    }

    public function getNewPresetViewSortPosition(): ?string
    {
        return $this->newPresetViewSortPosition;
    }

    public function hasPresetViewLockIcon(): ?bool
    {
        return $this->evaluateNullableBool($this->presetViewLockIcon);
    }

    public function shouldCreateFromPresetWithQuery(): ?bool
    {
        return $this->evaluateNullableBool($this->createFromPresetWithQuery);
    }

    public function hasManagedDefaultViews(): ?bool
    {
        return $this->evaluateNullableBool($this->managedDefaultViews);
    }

    protected function evaluateNullableBool(bool|Closure|null $value): ?bool
    {
        return $value === null ? null : (bool) $this->evaluate($value);
    }
}
