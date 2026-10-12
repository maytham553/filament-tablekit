<?php

namespace Elite\TableViews;

use Closure;
use Elite\TableViews\Support\TableViewsOptions;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;

class TableViewsPlugin implements Plugin
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

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament('table-views');
    }

    public static function resolve(): TableViewsOptions
    {
        return new TableViewsOptions(TableViewsOptions::currentPlugin());
    }

    public function getId(): string
    {
        return 'table-views';
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
     * The database connection that holds the views table for this panel. Wins over config('table-views.connection').
     */
    public function connection(?string $connection): static
    {
        $this->connection = $connection;

        return $this;
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
}
