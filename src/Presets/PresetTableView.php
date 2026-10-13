<?php

namespace TableKit\Presets;

use Closure;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use TableKit\Support\CurrentContext;
use TableKit\Support\TableViewState;

/**
 * A developer-defined view returned from getPresetTableViews(). Pure configuration: it never touches the database.
 */
class PresetTableView
{
    use EvaluatesClosures;

    public const PART_FILTERS = 'filters';

    public const PART_SEARCH = 'search';

    public const PART_SORT = 'sort';

    public const PART_COLUMNS = 'columns';

    public const PART_GROUPING = 'grouping';

    public const PART_TAB = 'tab';

    public const PARTS = [
        self::PART_FILTERS,
        self::PART_SEARCH,
        self::PART_SORT,
        self::PART_COLUMNS,
        self::PART_GROUPING,
        self::PART_TAB,
    ];

    protected string $key = '';

    protected string|Closure|null $label = null;

    protected ?string $icon = null;

    protected ?string $color = null;

    protected bool|Closure $favorite = false;

    /** @var array<string, mixed>|null */
    protected ?array $defaultFilters = null;

    protected ?string $defaultSortColumn = null;

    protected ?string $defaultSortDirection = null;

    /** @var array<int, array{column: string, direction: string}> */
    protected array $defaultSorts = [];

    protected ?string $defaultSearch = null;

    /** @var array<string, mixed>|null */
    protected ?array $defaultColumnSearches = null;

    /** @var array<string>|null */
    protected ?array $defaultToggledColumns = null;

    /** @var array<string>|null */
    protected ?array $defaultColumnOrder = null;

    /** @var array<string>|null */
    protected ?array $defaultFavoriteFilters = null;

    protected ?string $defaultGroupingColumn = null;

    protected ?string $defaultGroupingDirection = null;

    protected ?Closure $modifyQueryUsing = null;

    protected mixed $badge = null;

    protected string|Closure|null $badgeColor = null;

    protected string|Closure|null $tooltip = null;

    protected bool|Closure $visible = true;

    protected bool|Closure $hidden = false;

    protected bool|Closure $isDefault = false;

    /** @var array<string, true> */
    protected array $preserved = [];

    protected mixed $livewire = null;

    protected ?int $badgeCacheSeconds = null;

    protected string $cacheScope = '';

    /** @var array<string, mixed> */
    protected array $resolved = [];

    public static function make(): static
    {
        return new static;
    }

    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function livewire(mixed $livewire): static
    {
        $this->livewire = $livewire;

        return $this;
    }

    /** The component's table key, so a cached badge is never shared between tables. */
    public function cacheScope(string $scope): static
    {
        $this->cacheScope = $scope;

        return $this;
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** An icon key resolved through the `tablekit::icons.<key>` alias, like the icon of a user view. */
    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function favorite(bool|Closure $condition = true): static
    {
        $this->favorite = $condition;

        return $this;
    }

    /** @param  array<string, mixed>  $filters  raw `tableFilters` state, keyed by filter name */
    public function defaultFilters(?array $filters): static
    {
        $this->defaultFilters = $filters;

        return $this;
    }

    /**
     * @param  string|array<string, string>  $column  a column name, or `['column' => 'direction', ...]` for a multi-sort (first = highest priority)
     */
    public function defaultSort(string|array|null $column, string $direction = 'asc'): static
    {
        $sorts = [];

        if (is_array($column)) {
            foreach ($column as $name => $sortDirection) {
                if (is_string($name) && $name !== '' && ! isset($sorts[$name])) {
                    $sorts[$name] = ['column' => $name, 'direction' => strtolower((string) $sortDirection) === 'desc' ? 'desc' : 'asc'];
                }
            }
        } elseif (is_string($column) && $column !== '') {
            $sorts[$column] = ['column' => $column, 'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc'];
        }

        $this->defaultSorts = array_values($sorts);
        $this->defaultSortColumn = $this->defaultSorts[0]['column'] ?? null;
        $this->defaultSortDirection = $this->defaultSorts[0]['direction'] ?? null;

        return $this;
    }

    public function defaultSearch(?string $search): static
    {
        $this->defaultSearch = $search;

        return $this;
    }

    /** @param  array<string, mixed>  $searches */
    public function defaultColumnSearches(?array $searches): static
    {
        $this->defaultColumnSearches = $searches;

        return $this;
    }

    /** @param  array<string>  $columns  the toggleable columns that are shown; the others are hidden */
    public function defaultToggledColumns(?array $columns): static
    {
        $this->defaultToggledColumns = $columns === null ? null : array_values(array_map('strval', $columns));

        return $this;
    }

    /**
     * Column order and visibility in one call: the listed columns first, in this order. Toggleable columns that are not listed are hidden,
     * the other unlisted columns are appended in their code order.
     *
     * @param  array<string>|null  $columns
     */
    public function defaultColumns(?array $columns): static
    {
        $this->defaultToggledColumns($columns);
        $this->defaultColumnOrder = $columns === null ? null : array_values(array_unique(array_map('strval', $columns)));

        return $this;
    }

    /**
     * The filters whose chip is pinned in the quick filter bar while this preset is active. Replaces the filters marked with `favorite()`.
     *
     * @param  array<string>|null  $filters  filter names
     */
    public function defaultFavoriteFilters(?array $filters): static
    {
        $this->defaultFavoriteFilters = $filters === null ? null : array_values(array_unique(array_map('strval', $filters)));

        return $this;
    }

    public function defaultGrouping(?string $column, string $direction = 'asc'): static
    {
        $this->defaultGroupingColumn = $column !== null && $column !== '' ? $column : null;
        $this->defaultGroupingDirection = $this->defaultGroupingColumn === null ? null : (strtolower($direction) === 'desc' ? 'desc' : 'asc');

        return $this;
    }

    /**
     * Hidden query, applied only while this preset is the active view.
     *
     * The closure runs inside a nested where group on a fresh builder of the table's model, so an `orWhere` can never
     * leave the table's own constraints (tenant, relation manager parent, Filament filters and search). Only where
     * constraints are carried over: put ordering in `defaultSort()` and joins or eager loads in the table query.
     * It is a filter for convenience, not an access-control boundary.
     */
    public function modifyQueryUsing(?Closure $callback): static
    {
        $this->modifyQueryUsing = $callback;

        return $this;
    }

    /** Evaluated at most once per request, and only when the favorites bar renders. Use cacheBadgeFor() for anything that queries. */
    public function badge(mixed $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /** Keeps the badge value in the app cache, per user, tenant and table, so its closure does not run on every request. */
    public function cacheBadgeFor(?int $seconds): static
    {
        $this->badgeCacheSeconds = $seconds !== null && $seconds > 0 ? $seconds : null;

        return $this;
    }

    public function badgeColor(string|Closure|null $color): static
    {
        $this->badgeColor = $color;

        return $this;
    }

    public function tooltip(string|Closure|null $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function hidden(bool|Closure $condition = true): static
    {
        $this->hidden = $condition;

        return $this;
    }

    public function default(bool|Closure $condition = true): static
    {
        $this->isDefault = $condition;

        return $this;
    }

    public function preserveAll(bool $condition = true): static
    {
        return $this->preserve(self::PARTS, $condition);
    }

    public function preserveFilters(bool $condition = true): static
    {
        return $this->preserve([self::PART_FILTERS], $condition);
    }

    public function preserveToggledColumns(bool $condition = true): static
    {
        return $this->preserve([self::PART_COLUMNS], $condition);
    }

    public function preserveSort(bool $condition = true): static
    {
        return $this->preserve([self::PART_SORT], $condition);
    }

    public function preserveSearch(bool $condition = true): static
    {
        return $this->preserve([self::PART_SEARCH], $condition);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        $label = $this->evaluate($this->label);

        return is_string($label) && $label !== '' ? $label : (string) str($this->key)->headline();
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function isFavorite(): bool
    {
        return (bool) $this->evaluate($this->favorite);
    }

    public function isDefault(): bool
    {
        return (bool) $this->evaluate($this->isDefault);
    }

    public function isVisible(): bool
    {
        return ! $this->evaluate($this->hidden) && (bool) $this->evaluate($this->visible);
    }

    public function hasQuery(): bool
    {
        return $this->modifyQueryUsing !== null;
    }

    public function getBadge(): string|int|float|null
    {
        return $this->once('badge', function (): string|int|float|null {
            if ($this->badgeCacheSeconds === null || $this->cacheScope === '' || ! $this->badge instanceof Closure) {
                return $this->resolveBadge();
            }

            $cached = Cache::remember(
                'tablekit.badge.'.md5($this->cacheScope.'|'.CurrentContext::userId().'|'.CurrentContext::tenantType().'|'.CurrentContext::tenantId().'|'.$this->key),
                $this->badgeCacheSeconds,
                fn (): array => ['value' => $this->resolveBadge()],
            );

            return $cached['value'] ?? null;
        });
    }

    protected function resolveBadge(): string|int|float|null
    {
        $badge = $this->evaluate($this->badge);

        return is_scalar($badge) && $badge !== '' ? $badge : null;
    }

    public function getBadgeColor(): string
    {
        return $this->once('badgeColor', function (): string {
            $color = $this->evaluate($this->badgeColor);

            return is_string($color) && $color !== '' ? $color : 'primary';
        });
    }

    public function getTooltip(): ?string
    {
        return $this->once('tooltip', function (): ?string {
            $tooltip = $this->evaluate($this->tooltip);

            return is_string($tooltip) && $tooltip !== '' ? $tooltip : null;
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    protected function once(string $name, Closure $resolve): mixed
    {
        if (! array_key_exists($name, $this->resolved)) {
            $this->resolved[$name] = $resolve();
        }

        return $this->resolved[$name];
    }

    /** @return array<string> */
    public function getPreservedParts(): array
    {
        return array_keys($this->preserved);
    }

    /**
     * The parts this preset sets itself. The others fall back to the table defaults when the preset is applied.
     *
     * @return array<string>
     */
    public function getDefinedParts(): array
    {
        return array_keys(array_filter([
            self::PART_FILTERS => $this->defaultFilters !== null || $this->defaultFavoriteFilters !== null,
            self::PART_SEARCH => $this->defaultSearch !== null || $this->defaultColumnSearches !== null,
            self::PART_SORT => $this->defaultSortColumn !== null,
            self::PART_COLUMNS => $this->defaultToggledColumns !== null,
            self::PART_GROUPING => $this->defaultGroupingColumn !== null,
        ]));
    }

    /** @return array<string>|null */
    public function getDefaultToggledColumns(): ?array
    {
        return $this->defaultToggledColumns;
    }

    public function toState(): TableViewState
    {
        return TableViewState::fromArray([
            'filters' => $this->defaultFilters ?? [],
            'search' => $this->defaultSearch ?? '',
            'columnSearches' => $this->defaultColumnSearches ?? [],
            'sort' => ['column' => $this->defaultSortColumn, 'direction' => $this->defaultSortDirection],
            'sorts' => $this->defaultSorts,
            'grouping' => ['column' => $this->defaultGroupingColumn, 'direction' => $this->defaultGroupingDirection],
            'columnOrder' => $this->defaultColumnOrder ?? [],
            'favoriteFilters' => $this->defaultFavoriteFilters,
        ]);
    }

    /**
     * The closure's wheres go into one nested group on the query, so an `orWhere` inside it only widens the preset
     * and never the constraints already on the query. Scopes the closure removes (`withoutGlobalScopes()`,
     * `withTrashed()`) are not carried over: every global scope of the table query stays in force.
     * The closure runs once per call.
     */
    public function applyQuery(Builder $query): Builder
    {
        if ($this->modifyQueryUsing === null) {
            return $query;
        }

        $nested = $query->getModel()->newQueryWithoutRelationships();

        $this->evaluate(
            $this->modifyQueryUsing,
            ['query' => $nested, 'livewire' => $this->livewire],
            [Builder::class => $nested],
        );

        $query->setEagerLoads(array_merge($query->getEagerLoads(), $nested->getEagerLoads()));
        $query->getQuery()->addNestedWhereQuery($nested->getQuery());

        return $query;
    }

    /**
     * @param  array<string>  $parts
     */
    protected function preserve(array $parts, bool $condition): static
    {
        foreach ($parts as $part) {
            if ($condition) {
                $this->preserved[$part] = true;
            } else {
                unset($this->preserved[$part]);
            }
        }

        return $this;
    }
}
