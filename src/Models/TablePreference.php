<?php

namespace TableKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use TableKit\Support\CurrentContext;
use TableKit\Support\TableKitOptions;

/**
 * @property int $id
 * @property ?string $user_type
 * @property string $user_id
 * @property ?string $tenant_type
 * @property ?string $tenant_id
 * @property string $resource
 * @property string $view_type
 * @property string $view_key
 * @property ?bool $is_favorite
 * @property bool $is_hidden
 * @property ?int $sort
 * @property bool $is_default
 */
class TablePreference extends Model
{
    public const TYPE_PRESET = 'preset';

    public const TYPE_USER = 'user';

    protected $fillable = [
        'user_type',
        'user_id',
        'tenant_type',
        'tenant_id',
        'resource',
        'view_type',
        'view_key',
        'is_favorite',
        'is_hidden',
        'sort',
        'is_default',
    ];

    protected $casts = [
        'user_id' => 'string',
        'view_key' => 'string',
        'is_favorite' => 'boolean',
        'is_hidden' => 'boolean',
        'sort' => 'integer',
        'is_default' => 'boolean',
    ];

    public function getTable(): string
    {
        return TableKitOptions::resolve()->preferencesTableName();
    }

    public function getConnectionName(): ?string
    {
        return TableKitOptions::resolve()->connection();
    }

    public function scopeOwnedBy(Builder $query, ?string $userType, int|string $userId, ?Model $tenant = null): Builder
    {
        $userType === null ? $query->whereNull('user_type') : $query->where('user_type', $userType);
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

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('view_type', $type);
    }

    /**
     * Every read and write goes through this so preferences never leak across users, tenants or tables.
     */
    public static function forCurrentContext(string $resourceKey): Builder
    {
        $userId = CurrentContext::userId();

        if ($userId === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()
            ->ownedBy(CurrentContext::userType(), $userId, CurrentContext::tenant())
            ->forResource($resourceKey);
    }

    /**
     * Find or create the row of one view for the current user, tenant and table, then apply the changes.
     * A unique violation from a concurrent request retries once against the row that won.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function upsertFor(string $resourceKey, string $type, string $key, array $changes): ?self
    {
        $userId = CurrentContext::userId();

        if ($userId === null) {
            return null;
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $row = static::forCurrentContext($resourceKey)->ofType($type)->where('view_key', $key)->first()
                ?? new static([
                    'user_type' => CurrentContext::userType(),
                    'user_id' => (string) $userId,
                    'tenant_type' => CurrentContext::tenantType(),
                    'tenant_id' => CurrentContext::tenantId(),
                    'resource' => $resourceKey,
                    'view_type' => $type,
                    'view_key' => $key,
                ]);

            $row->fill($changes);

            try {
                $row->getConnection()->transaction(fn () => $row->save());

                return $row;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 1) {
                    throw $exception;
                }
            }
        }

        return null;
    }
}
