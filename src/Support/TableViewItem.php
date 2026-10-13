<?php

namespace Elite\TableViews\Support;

use Elite\TableViews\Models\TablePreference;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Presets\PresetTableView;

/**
 * One entry of the favorites bar or the view manager: either a preset or a user view, with the user's preferences merged in.
 */
final class TableViewItem
{
    public function __construct(
        public readonly string $type,
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $icon,
        public readonly string $color,
        public readonly bool $isFavorite,
        public readonly bool $isDefault,
        public readonly ?int $sort,
        public readonly ?PresetTableView $preset = null,
        public readonly ?TableView $view = null,
    ) {}

    public function isPreset(): bool
    {
        return $this->type === TablePreference::TYPE_PRESET;
    }
}
