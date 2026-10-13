<?php

namespace TableKit\Support;

use Filament\Support\Facades\FilamentColor;
use Illuminate\Support\Collection;
use TableKit\Models\TablePreference;
use TableKit\Models\TableView;
use TableKit\Presets\PresetTableView;

/**
 * Merges developer presets, the user's own views and the user's preference rows into ordered items and manager sections.
 * Pure data in, data out: it never queries.
 */
final class TableViewCatalog
{
    public const DEFAULT_KEY = 'default';

    /**
     * @param  Collection<int, TableViewItem>  $presets  every shown preset, in display order
     * @param  Collection<int, TableViewItem>  $userViews  every shown user view, in display order
     */
    public function __construct(
        public readonly Collection $presets,
        public readonly Collection $userViews,
    ) {}

    /**
     * @param  array<string, PresetTableView>  $presets  visible presets keyed by key, in developer order
     * @param  Collection<int, TableView>  $views  the user's views of this table, ordered by sort then id
     * @param  Collection<int, TablePreference>  $preferences
     */
    public static function build(array $presets, Collection $views, Collection $preferences, bool $manageable, string $newPosition = 'after'): self
    {
        $rows = $preferences->keyBy(fn (TablePreference $row): string => $row->view_type.':'.$row->view_key);

        $presetItems = collect();
        $ranked = collect();
        $unranked = collect();

        foreach ($presets as $key => $preset) {
            if ($key === self::DEFAULT_KEY) {
                continue;
            }

            /** @var ?TablePreference $row */
            $row = $rows->get(TablePreference::TYPE_PRESET.':'.$key);

            if ($row?->is_hidden) {
                continue;
            }

            $sort = $manageable ? $row?->sort : null;

            $item = new TableViewItem(
                type: TablePreference::TYPE_PRESET,
                key: (string) $key,
                label: $preset->getLabel(),
                icon: $preset->getIcon(),
                color: self::color($preset->getColor()),
                isFavorite: $manageable && $row?->is_favorite !== null ? (bool) $row->is_favorite : $preset->isFavorite(),
                isDefault: (bool) $row?->is_default,
                sort: $sort,
                preset: $preset,
            );

            ($sort === null ? $unranked : $ranked)->push($item);
        }

        $ranked = $ranked->sortBy('sort', SORT_REGULAR)->values();
        $presetItems = $newPosition === 'before' ? $unranked->concat($ranked) : $ranked->concat($unranked);

        $userItems = $views
            ->filter(fn (TableView $view): bool => ! $rows->get(TablePreference::TYPE_USER.':'.$view->getKey())?->is_hidden)
            ->map(fn (TableView $view): TableViewItem => new TableViewItem(
                type: TablePreference::TYPE_USER,
                key: (string) $view->getKey(),
                label: $view->name,
                icon: $view->icon,
                color: $view->resolvedColor(),
                isFavorite: (bool) $view->is_favorite,
                isDefault: (bool) $rows->get(TablePreference::TYPE_USER.':'.$view->getKey())?->is_default,
                sort: (int) $view->sort,
                view: $view,
            ))
            ->values();

        return new self($presetItems->values(), $userItems);
    }

    /** @return Collection<int, TableViewItem> */
    public function favoritePresets(): Collection
    {
        return $this->presets->filter(fn (TableViewItem $item): bool => $item->isFavorite)->values();
    }

    /** @return Collection<int, TableViewItem> */
    public function favoriteUserViews(): Collection
    {
        return $this->userViews->filter(fn (TableViewItem $item): bool => $item->isFavorite)->values();
    }

    /** @return Collection<int, TableViewItem> */
    public function favorites(): Collection
    {
        return $this->favoritePresets()->concat($this->favoriteUserViews())->values();
    }

    /** @return Collection<int, TableViewItem> */
    public function myViews(): Collection
    {
        return $this->userViews->reject(fn (TableViewItem $item): bool => $item->isFavorite)->values();
    }

    /** @return Collection<int, TableViewItem> */
    public function presetViews(): Collection
    {
        return $this->presets->reject(fn (TableViewItem $item): bool => $item->isFavorite)->values();
    }

    public function find(string $type, string $key): ?TableViewItem
    {
        $items = $type === TablePreference::TYPE_PRESET ? $this->presets : ($type === TablePreference::TYPE_USER ? $this->userViews : collect());

        return $items->first(fn (TableViewItem $item): bool => $item->key === $key);
    }

    public function defaultItem(): ?TableViewItem
    {
        return $this->presets->first(fn (TableViewItem $item): bool => $item->isDefault)
            ?? $this->userViews->first(fn (TableViewItem $item): bool => $item->isDefault);
    }

    public function count(): int
    {
        return $this->presets->count() + $this->userViews->count();
    }

    protected static function color(?string $color): string
    {
        return filled($color) && array_key_exists($color, FilamentColor::getColors()) ? $color : 'primary';
    }
}
