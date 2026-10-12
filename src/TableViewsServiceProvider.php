<?php

namespace Elite\TableViews;

use Closure;
use Elite\TableViews\Concerns\HasTableViews;
use Elite\TableViews\Support\FavoriteFilters;
use Elite\TableViews\Support\TableViewsOptions;
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

class TableViewsServiceProvider extends PackageServiceProvider
{
    /** @var array<string, bool> */
    protected static array $usageCache = [];

    /** True once the plugin itself registered `BaseFilter::favorite()`, so a later boot can tell its own macro from a foreign one. */
    protected static bool $favoriteMacroRegistered = false;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('table-views')
            ->hasConfigFile()
            ->hasViews('table-views')
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        if (config('table-views.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $now = time();

        $this->publishes([
            __DIR__.'/../database/migrations/2026_01_01_000000_create_table_views_table.php' => database_path(
                'migrations/'.date('Y_m_d_His', $now).'_create_table_views_table.php'
            ),
            __DIR__.'/../database/migrations/2026_01_01_000001_create_table_view_preferences_table.php' => database_path(
                'migrations/'.date('Y_m_d_His', $now + 1).'_create_table_view_preferences_table.php'
            ),
        ], 'table-views-migrations');

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
            fn (array $scopes): string => $this->renderScopedView($scopes, 'table-views::sort-button'),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn (array $scopes): string => $this->renderScopedView($scopes, 'table-views::columns-fallback'),
        );

        // Filament renders this hook without scopes: the view checks the component it is rendered in.
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_AFTER,
            fn (): string => view('table-views::quick-filters')->render(),
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
            Log::warning('table-views: BaseFilter::favorite() is already defined by another package, so the plugin did not register it. Use quickFilterFavorite() to mark quick filter favorites.');

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

        if (! TableViewsOptions::resolve()->favoritesBarEnabled()) {
            return '';
        }

        return view('table-views::favorites-bar')->render();
    }

    public static function usesTableViews(string $class): bool
    {
        return static::$usageCache[$class] ??= class_exists($class)
            && in_array(HasTableViews::class, class_uses_recursive($class), true);
    }

    /**
     * Only aliases nobody registered yet, so an app remapping them (e.g. to another icon set) stays in charge.
     */
    protected function registerIconDefaults(): void
    {
        $defaults = [
            'table-views::favorites-bar.default' => 'heroicon-m-arrow-path',
            'table-views::quick-save' => 'heroicon-m-bookmark',
            'table-views::manager.open' => 'heroicon-m-queue-list',
            'table-views::manager.preset' => 'heroicon-m-lock-closed',
            'table-views::manager.user-view' => 'heroicon-m-user',
            'table-views::manager.favorite' => 'heroicon-s-star',
            'table-views::manager.unfavorite' => 'heroicon-o-star',
            'table-views::manager.default' => 'heroicon-o-home',
            'table-views::manager.remove-default' => 'heroicon-s-home',
            'table-views::manager.edit' => 'heroicon-m-pencil-square',
            'table-views::manager.delete' => 'heroicon-m-trash',
            'table-views::manager.active' => 'heroicon-m-check',
            'table-views::reorder.handle' => 'heroicon-m-bars-2',
            'table-views::sort.open' => 'heroicon-m-bars-arrow-down',
            'table-views::sort.asc' => 'heroicon-m-arrow-up',
            'table-views::sort.desc' => 'heroicon-m-arrow-down',
            'table-views::sort.remove' => 'heroicon-m-x-mark',
            'table-views::quick-filters.chevron' => 'heroicon-m-chevron-down',
            'table-views::quick-filters.favorite' => 'heroicon-s-star',
            'table-views::quick-filters.unfavorite' => 'heroicon-o-star',
            'table-views::quick-filters.clear' => 'heroicon-m-x-mark',
            'table-views::columns.open' => 'heroicon-m-view-columns',
            'table-views::columns.reset' => 'heroicon-m-arrow-uturn-left',
            'table-views::icons.star' => 'heroicon-m-star',
            'table-views::icons.heart' => 'heroicon-m-heart',
            'table-views::icons.bookmark' => 'heroicon-m-bookmark',
            'table-views::icons.flag' => 'heroicon-m-flag',
            'table-views::icons.bolt' => 'heroicon-m-bolt',
            'table-views::icons.fire' => 'heroicon-m-fire',
            'table-views::icons.funnel' => 'heroicon-m-funnel',
            'table-views::icons.clock' => 'heroicon-m-clock',
            'table-views::icons.user' => 'heroicon-m-user',
            'table-views::icons.users' => 'heroicon-m-users',
            'table-views::icons.tag' => 'heroicon-m-tag',
            'table-views::icons.eye' => 'heroicon-m-eye',
            'table-views::icons.chart-bar' => 'heroicon-m-chart-bar',
            'table-views::icons.currency' => 'heroicon-m-banknotes',
            'table-views::icons.check-circle' => 'heroicon-m-check-circle',
            'table-views::icons.warning' => 'heroicon-m-exclamation-triangle',
            'table-views::icons.folder' => 'heroicon-m-folder',
            'table-views::icons.inbox' => 'heroicon-m-inbox',
            'table-views::icons.truck' => 'heroicon-m-truck',
            'table-views::icons.calendar' => 'heroicon-m-calendar',
            'table-views::icons.shopping-cart' => 'heroicon-m-shopping-cart',
            'table-views::icons.archive' => 'heroicon-m-archive-box',
            'table-views::icons.document' => 'heroicon-m-document-text',
            'table-views::icons.bell' => 'heroicon-m-bell',
            'table-views::icons.home' => 'heroicon-m-home',
            'table-views::icons.globe' => 'heroicon-m-globe-alt',
        ];

        FilamentIcon::register(array_filter(
            $defaults,
            fn (string $icon, string $alias): bool => FilamentIcon::resolve($alias) === null,
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
