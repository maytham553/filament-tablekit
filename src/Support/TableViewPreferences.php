<?php

namespace Elite\TableViews\Support;

use Elite\TableViews\Models\TablePreference;
use Elite\TableViews\Models\TableView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writes the user's favorite, order and default choices. Callers pass a catalog built from fresh data,
 * and every query is scoped to the current user, tenant and table.
 */
class TableViewPreferences
{
    public function __construct(
        protected string $resourceKey,
        protected TableViewCatalog $catalog,
        protected TableViewsOptions $options,
    ) {}

    public function toggleFavorite(string $type, string $key): bool
    {
        $item = $this->catalog->find($type, $key);

        if (! $item) {
            return false;
        }

        $favorite = ! $item->isFavorite;

        if ($item->isPreset()) {
            if (! $this->options->presetViewsManageable()) {
                return false;
            }

            TablePreference::upsertFor($this->resourceKey, $type, $key, [
                'is_favorite' => $favorite === $item->preset->isFavorite() ? null : $favorite,
            ]);

            return true;
        }

        TableView::forCurrentContext($this->resourceKey)
            ->whereKey($item->view->getKey())
            ->update(['is_favorite' => $favorite]);

        return true;
    }

    /**
     * Stores a new order for one section: the items of `$type` that share the favorite state `$favorite`.
     * `$keys` must be exactly the keys of that section; anything else is refused and nothing is written.
     */
    public function reorder(string $type, bool $favorite, mixed $keys): bool
    {
        $all = match ($type) {
            TablePreference::TYPE_PRESET => $this->options->presetViewsManageable() ? $this->catalog->presets : null,
            TablePreference::TYPE_USER => $this->catalog->userViews,
            default => null,
        };

        if ($all === null) {
            return false;
        }

        $section = $all->filter(fn (TableViewItem $item): bool => $item->isFavorite === $favorite)->pluck('key')->map(fn ($key): string => (string) $key)->values()->all();
        $keys = ReorderedKeys::validate($keys, $section);

        if ($keys === null) {
            return false;
        }

        if ($keys === $section) {
            return true;
        }

        $order = ReorderedKeys::mergeInto($all->pluck('key')->map(fn ($key): string => (string) $key)->all(), $section, $keys);

        $this->writeOrder($type, $order, $all);

        return true;
    }

    /**
     * Swaps the item with its neighbour inside its section (same type, same favorite state).
     */
    public function move(string $type, string $key, string $direction): bool
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            return false;
        }

        $item = $this->catalog->find($type, $key);

        if (! $item) {
            return false;
        }

        $all = $item->isPreset() ? $this->catalog->presets : $this->catalog->userViews;
        $section = $all->filter(fn (TableViewItem $other): bool => $other->isFavorite === $item->isFavorite)->pluck('key')->map(fn ($value): string => (string) $value)->values()->all();

        $from = array_search($key, $section, true);
        $to = $from + ($direction === 'up' ? -1 : 1);

        if ($from === false || ! isset($section[$to])) {
            return false;
        }

        [$section[$from], $section[$to]] = [$section[$to], $section[$from]];

        return $this->reorder($type, $item->isFavorite, $section);
    }

    /**
     * Marks the item as the user's default, or clears it when it already is. One default per user, tenant and table.
     */
    public function toggleDefault(string $type, string $key): bool
    {
        $item = $this->catalog->find($type, $key);

        if (! $item) {
            return false;
        }

        DB::connection($this->options->connection())->transaction(function () use ($item, $type, $key): void {
            TablePreference::forCurrentContext($this->resourceKey)->where('is_default', true)->update(['is_default' => false]);

            if (! $item->isDefault) {
                TablePreference::upsertFor($this->resourceKey, $type, $key, ['is_default' => true]);
            }
        });

        return true;
    }

    /**
     * @param  array<int, string>  $order
     * @param  Collection<int, TableViewItem>  $current
     */
    protected function writeOrder(string $type, array $order, Collection $current): void
    {
        $currentSort = $current->mapWithKeys(fn (TableViewItem $item): array => [$item->key => $item->sort]);

        DB::connection($this->options->connection())->transaction(function () use ($type, $order, $currentSort): void {
            foreach ($order as $index => $key) {
                $sort = $type === TablePreference::TYPE_PRESET ? $index : $index + 1;

                if ($currentSort->get($key) === $sort) {
                    continue;
                }

                if ($type === TablePreference::TYPE_PRESET) {
                    TablePreference::upsertFor($this->resourceKey, $type, $key, ['sort' => $sort]);

                    continue;
                }

                TableView::forCurrentContext($this->resourceKey)->whereKey($key)->update(['sort' => $sort]);
            }
        });
    }
}
