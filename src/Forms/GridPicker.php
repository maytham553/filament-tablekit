<?php

namespace Elite\TableViews\Forms;

use Closure;
use Filament\Forms\Components\Field;

/**
 * A radio group drawn as a grid of visual cells. The state is the chosen key (a string, or null for "none").
 * Subclasses decide what a cell looks like; the Blade view and the Field base are the only Filament specific parts.
 */
abstract class GridPicker extends Field
{
    /** @var view-string */
    protected string $view = 'table-views::forms.grid-picker';

    /** @var array<int, string>|Closure */
    protected array|Closure $keys = [];

    /**
     * @param  array<int, string>|Closure  $keys
     */
    public function keys(array|Closure $keys): static
    {
        $this->keys = $keys;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getKeys(): array
    {
        $keys = $this->evaluate($this->keys);

        return array_values(array_unique(array_filter(is_array($keys) ? $keys : [], is_string(...))));
    }

    /** 'color' or 'icon': picks the cell markup and the grid sizing. */
    abstract public function getKind(): string;

    /**
     * Every cell in display order. `search` is the lowercase text the search box matches against.
     *
     * @return array<int, array{value: string, label: string, search: string, style: ?string, icon: mixed, none: bool}>
     */
    abstract public function getItems(): array;

    public function isSearchable(): bool
    {
        return false;
    }
}
