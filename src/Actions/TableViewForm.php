<?php

namespace Elite\TableViews\Actions;

use Closure;
use Elite\TableViews\Forms\ColorGridPicker;
use Elite\TableViews\Forms\IconGridPicker;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Support\TableViewsOptions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * Name, color and icon fields shared by Quick Save and Edit, so both validate the same way.
 */
class TableViewForm
{
    /**
     * @param  Closure(): (int|string|null)|null  $exceptId  the view being edited, ignored by the unique name check
     * @return array<int, Component>
     */
    public static function fields(string $resourceKey, ?Closure $exceptId = null): array
    {
        $options = TableViewsOptions::resolve();

        return [
            TextInput::make('name')
                ->label(__('table-views::quick-save.fields.name'))
                ->required()
                ->maxLength($options->maxNameLength())
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($resourceKey, $exceptId): void {
                    if (is_string($value) && TableView::nameTaken($value, $resourceKey, $exceptId ? $exceptId() : null)) {
                        $fail(__('table-views::validation.name_taken'));
                    }
                }),
            ColorGridPicker::make('color')
                ->label(__('table-views::quick-save.fields.color'))
                ->colors(fn (): array => $options->colors())
                ->in(fn (): array => $options->colors())
                ->required(),
            IconGridPicker::make('icon')
                ->label(__('table-views::quick-save.fields.icon'))
                ->icons(fn (): array => $options->icons())
                ->in(fn (): array => $options->icons()),
        ];
    }
}
