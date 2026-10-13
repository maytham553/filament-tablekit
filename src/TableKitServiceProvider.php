<?php

namespace TableKit;

use Closure;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Filament\Tables\View\TablesRenderHook;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TableKit\Concerns\HasTableKit;
use TableKit\Support\ColorPalette;
use TableKit\Support\FavoriteFilters;
use TableKit\Support\IconCatalog;
use TableKit\Support\TableKitOptions;

class TableKitServiceProvider extends PackageServiceProvider
{
    /** @var array<string, bool> */
    protected static array $usageCache = [];

    /** True once the plugin itself registered `BaseFilter::favorite()`, so a later boot can tell its own macro from a foreign one. */
    protected static bool $favoriteMacroRegistered = false;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('tablekit')
            ->hasConfigFile()
            ->hasViews('tablekit')
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->isBooted() ? ColorPalette::register() : $this->app->booting(ColorPalette::register(...));
    }

    public function packageBooted(): void
    {
        if (config('tablekit.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $now = time();

        $this->publishes([
            __DIR__.'/../database/migrations/2026_01_01_000000_create_tablekit_views_table.php' => database_path(
                'migrations/'.date('Y_m_d_His', $now).'_create_tablekit_views_table.php'
            ),
            __DIR__.'/../database/migrations/2026_01_01_000001_create_tablekit_preferences_table.php' => database_path(
                'migrations/'.date('Y_m_d_His', $now + 1).'_create_tablekit_preferences_table.php'
            ),
        ], 'tablekit-migrations');

        $this->registerIconDefaults();
        $this->registerFavoriteFilterMacro();

        Table::configureUsing(function (Table $table): void {
            $livewire = $table->getLivewire();

            if (! static::usesTableViews($livewire::class)) {
                return;
            }

            $table->modifyQueryUsing(
                fn (Builder $query): Builder => (fn (): Builder => $this->applyActiveTablePresetQuery($query))->call($livewire)
            );
        });

        FilamentView::registerRenderHook(
            TablesRenderHook::HEADER_BEFORE,
            fn (array $scopes): string => $this->renderFavoritesBar($scopes, widgets: false),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::HEADER_AFTER,
            fn (array $scopes): string => $this->renderFavoritesBar($scopes, widgets: true),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_GROUPING_SELECTOR_AFTER,
            fn (array $scopes): string => $this->renderScopedView($scopes, 'tablekit::sort-button'),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn (array $scopes): string => $this->renderScopedView($scopes, 'tablekit::columns-fallback'),
        );

        // Filament renders this hook without scopes: the view checks the component it is rendered in.
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_AFTER,
            fn (): string => view('tablekit::quick-filters')->render(),
        );
    }

    /**
     * @param  array<int, mixed>  $scopes
     */
    protected function renderScopedView(array $scopes, string $view): string
    {
        $class = $scopes[0] ?? null;

        if (! is_string($class) || ! static::usesTableViews($class)) {
            return '';
        }

        return view($view)->render();
    }

    /**
     * `->quickFilterFavorite()` is the plugin's own name and always available. `->favorite()` is the short form: it is registered only when nobody else owns
     * that name; when another package does, a warning is logged and `quickFilterFavorite()` is the way to mark a filter.
     */
    protected function registerFavoriteFilterMacro(): void
    {
        $mark = function (bool|Closure $condition = true): BaseFilter {
            /** @var BaseFilter $this */
            FavoriteFilters::mark($this, $condition);

            return $this;
        };

        BaseFilter::macro('quickFilterFavorite', $mark);

        if (static::$favoriteMacroRegistered && BaseFilter::hasMacro('favorite')) {
            return;
        }

        static::$favoriteMacroRegistered = false;

        if (method_exists(BaseFilter::class, 'favorite') || BaseFilter::hasMacro('favorite')) {
            Log::warning('tablekit: BaseFilter::favorite() is already defined by another package, so the plugin did not register it. Use quickFilterFavorite() to mark quick filter favorites.');

            return;
        }

        BaseFilter::macro('favorite', $mark);
        static::$favoriteMacroRegistered = true;
    }

    /**
     * Table widgets have a heading: their bar goes below it (HEADER_AFTER). Every other table gets it first (HEADER_BEFORE).
     *
     * @param  array<int, mixed>  $scopes
     */
    protected function renderFavoritesBar(array $scopes, bool $widgets): string
    {
        $class = $scopes[0] ?? null;

        if (! is_string($class) || ! static::usesTableViews($class) || is_subclass_of($class, TableWidget::class) !== $widgets) {
            return '';
        }

        if (! TableKitOptions::resolve()->favoritesBarEnabled()) {
            return '';
        }

        return view('tablekit::favorites-bar')->render();
    }

    public static function usesTableViews(string $class): bool
    {
        return static::$usageCache[$class] ??= class_exists($class)
            && in_array(HasTableKit::class, class_uses_recursive($class), true);
    }

    /**
     * Only aliases nobody registered yet, so an app remapping them (e.g. to another icon set) stays in charge.
     */
    protected function registerIconDefaults(): void
    {
        $defaults = [
            'tablekit::favorites-bar.default' => 'heroicon-m-arrow-path',
            'tablekit::quick-save' => 'heroicon-m-bookmark',
            'tablekit::manager.open' => 'heroicon-m-queue-list',
            'tablekit::manager.preset' => 'heroicon-m-lock-closed',
            'tablekit::manager.user-view' => 'heroicon-m-user',
            'tablekit::manager.favorite' => 'heroicon-s-star',
            'tablekit::manager.unfavorite' => 'heroicon-o-star',
            'tablekit::manager.default' => 'heroicon-o-home',
            'tablekit::manager.remove-default' => 'heroicon-s-home',
            'tablekit::manager.edit' => 'heroicon-m-pencil-square',
            'tablekit::manager.delete' => 'heroicon-m-trash',
            'tablekit::manager.active' => 'heroicon-m-check',
            'tablekit::reorder.handle' => 'heroicon-m-bars-2',
            'tablekit::sort.open' => 'heroicon-m-bars-arrow-down',
            'tablekit::sort.asc' => 'heroicon-m-arrow-up',
            'tablekit::sort.desc' => 'heroicon-m-arrow-down',
            'tablekit::sort.remove' => 'heroicon-m-x-mark',
            'tablekit::quick-filters.chevron' => 'heroicon-m-chevron-down',
            'tablekit::quick-filters.favorite' => 'heroicon-s-star',
            'tablekit::quick-filters.unfavorite' => 'heroicon-o-star',
            'tablekit::quick-filters.clear' => 'heroicon-m-x-mark',
            'tablekit::columns.open' => 'heroicon-m-view-columns',
            'tablekit::columns.reset' => 'heroicon-m-arrow-uturn-left',
        ];

        FilamentIcon::register(array_filter(
            [...$defaults, ...IconCatalog::aliases()],
            fn (string $icon, string $alias): bool => FilamentIcon::resolve($alias) === null,
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
