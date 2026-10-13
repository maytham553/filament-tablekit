<?php

namespace Elite\TableViews;

use Closure;
use Elite\TableViews\Concerns\HasTableViews;
use Elite\TableViews\Support\ColorPalette;
use Elite\TableViews\Support\FavoriteFilters;
use Elite\TableViews\Support\IconCatalog;
use Elite\TableViews\Support\TableViewsOptions;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
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

    public function packageRegistered(): void
    {
        $this->app->isBooted() ? ColorPalette::register() : $this->app->booting(ColorPalette::register(...));
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
            'table-views::favorites-bar.default' => Heroicon::ArrowPath,
            'table-views::quick-save' => Heroicon::Bookmark,
            'table-views::manager.open' => Heroicon::QueueList,
            'table-views::manager.preset' => Heroicon::LockClosed,
            'table-views::manager.user-view' => Heroicon::User,
            'table-views::manager.favorite' => Heroicon::Star,
            'table-views::manager.unfavorite' => Heroicon::OutlinedStar,
            'table-views::manager.default' => Heroicon::OutlinedHome,
            'table-views::manager.remove-default' => Heroicon::Home,
            'table-views::manager.edit' => Heroicon::PencilSquare,
            'table-views::manager.delete' => Heroicon::Trash,
            'table-views::manager.active' => Heroicon::Check,
            'table-views::reorder.handle' => Heroicon::Bars2,
            'table-views::sort.open' => Heroicon::BarsArrowDown,
            'table-views::sort.asc' => Heroicon::ArrowUp,
            'table-views::sort.desc' => Heroicon::ArrowDown,
            'table-views::sort.remove' => Heroicon::XMark,
            'table-views::quick-filters.chevron' => Heroicon::ChevronDown,
            'table-views::quick-filters.favorite' => Heroicon::Star,
            'table-views::quick-filters.unfavorite' => Heroicon::OutlinedStar,
        ];

        FilamentIcon::register(array_filter(
            [...$defaults, ...IconCatalog::aliases()],
            fn (Heroicon $icon, string $alias): bool => FilamentIcon::resolve($alias) === null,
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
