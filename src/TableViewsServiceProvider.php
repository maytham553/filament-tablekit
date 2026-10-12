<?php

namespace Elite\TableViews;

use Elite\TableViews\Concerns\HasTableViews;
use Elite\TableViews\Support\TableViewsOptions;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesRenderHook;
use Filament\Widgets\TableWidget;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TableViewsServiceProvider extends PackageServiceProvider
{
    /** @var array<string, bool> */
    protected static array $usageCache = [];

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

        $this->publishes([
            __DIR__.'/../database/migrations/2026_01_01_000000_create_table_views_table.php' => database_path(
                'migrations/'.date('Y_m_d_His').'_create_table_views_table.php'
            ),
        ], 'table-views-migrations');

        $this->registerIconDefaults();

        FilamentView::registerRenderHook(
            TablesRenderHook::HEADER_BEFORE,
            fn (array $scopes): string => $this->renderFavoritesBar($scopes, widgets: false),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::HEADER_AFTER,
            fn (array $scopes): string => $this->renderFavoritesBar($scopes, widgets: true),
        );
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
            'table-views::icons.star' => Heroicon::Star,
            'table-views::icons.heart' => Heroicon::Heart,
            'table-views::icons.bookmark' => Heroicon::Bookmark,
            'table-views::icons.flag' => Heroicon::Flag,
            'table-views::icons.bolt' => Heroicon::Bolt,
            'table-views::icons.fire' => Heroicon::Fire,
            'table-views::icons.funnel' => Heroicon::Funnel,
            'table-views::icons.clock' => Heroicon::Clock,
            'table-views::icons.user' => Heroicon::User,
            'table-views::icons.users' => Heroicon::Users,
            'table-views::icons.tag' => Heroicon::Tag,
            'table-views::icons.eye' => Heroicon::Eye,
            'table-views::icons.chart-bar' => Heroicon::ChartBar,
            'table-views::icons.currency' => Heroicon::Banknotes,
            'table-views::icons.check-circle' => Heroicon::CheckCircle,
            'table-views::icons.warning' => Heroicon::ExclamationTriangle,
            'table-views::icons.folder' => Heroicon::Folder,
            'table-views::icons.inbox' => Heroicon::Inbox,
        ];

        FilamentIcon::register(array_filter(
            $defaults,
            fn (Heroicon $icon, string $alias): bool => FilamentIcon::resolve($alias) === null,
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
