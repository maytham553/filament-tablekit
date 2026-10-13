<?php

namespace Elite\TableViews\Models;

use Elite\TableViews\Support\CurrentContext;
use Elite\TableViews\Support\TableViewsOptions;
use Elite\TableViews\Support\TableViewState;
use Elite\TableViews\Support\TableViewStateCast;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $user_id
 * @property string $name
 * @property ?string $icon
 * @property ?string $color
 * @property string $resource
 * @property TableViewState $state
 * @property bool $is_favorite
 * @property int $sort
 * @property ?string $tenant_type
 * @property ?string $tenant_id
 */
class TableView extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'icon',
        'color',
        'resource',
        'state',
        'is_favorite',
        'sort',
        'tenant_type',
        'tenant_id',
    ];

    protected $casts = [
        'user_id' => 'string',
        'state' => TableViewStateCast::class,
        'is_favorite' => 'boolean',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $view): void {
            TablePreference::query()
                ->ofType(TablePreference::TYPE_USER)
                ->where('view_key', (string) $view->getKey())
                ->where('resource', $view->resource)
                ->delete();
        });
    }

    public function getTable(): string
    {
        return TableViewsOptions::resolve()->tableName();
    }

    public function getConnectionName(): ?string
    {
        return TableViewsOptions::resolve()->connection();
    }

    public function user(): BelongsTo
    {
        $model = TableViewsOptions::resolve()->userModel();

        return $this->belongsTo($model, 'user_id', (new $model)->getKeyName());
    }

    public function resolvedColor(): string
    {
        return filled($this->color) && array_key_exists($this->color, FilamentColor::getColors()) ? $this->color : 'primary';
    }

    public function scopeOwnedBy(Builder $query, int|string $userId, ?Model $tenant = null): Builder
    {
        $query->where('user_id', (string) $userId);

        if ($tenant === null) {
            return $query->whereNull('tenant_type')->whereNull('tenant_id');
        }

        return $query
            ->where('tenant_type', $tenant->getMorphClass())
            ->where('tenant_id', (string) $tenant->getKey());
    }

    public function scopeForResource(Builder $query, string $resourceKey): Builder
    {
        return $query->where('resource', $resourceKey);
    }

    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->whereRaw('lower(name) = lower(?)', [$name]);
    }

    /**
     * Every read and write goes through this so views never leak across users, tenants or tables.
     */
    public static function forCurrentContext(string $resourceKey): Builder
    {
        $userId = CurrentContext::userId();

        if ($userId === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()
            ->ownedBy($userId, CurrentContext::tenant())
            ->forResource($resourceKey);
    }

    public static function nameTaken(string $name, string $resourceKey, int|string|null $exceptId = null): bool
    {
        return static::forCurrentContext($resourceKey)
            ->named(trim($name))
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    public static function nextSort(string $resourceKey): int
    {
        return ((int) static::forCurrentContext($resourceKey)->max('sort')) + 1;
    }
}
