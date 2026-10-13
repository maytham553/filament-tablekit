<?php

namespace TableKit\Actions;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use TableKit\Forms\ColorGridPicker;
use TableKit\Forms\IconGridPicker;
use TableKit\Models\TableView;
use TableKit\Support\TableKitOptions;

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
        $options = TableKitOptions::resolve();

        return [
            TextInput::make('name')
                ->label(__('tablekit::quick-save.fields.name'))
                ->required()
                ->maxLength($options->maxNameLength())
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($resourceKey, $exceptId): void {
                    if (is_string($value) && TableView::nameTaken($value, $resourceKey, $exceptId ? $exceptId() : null)) {
                        $fail(__('tablekit::validation.name_taken'));
                    }
                }),
            ColorGridPicker::make('color')
                ->label(__('tablekit::quick-save.fields.color'))
                ->colors(fn (): array => $options->colors())
                ->in(fn (): array => $options->colors())
                ->required(),
            IconGridPicker::make('icon')
                ->label(__('tablekit::quick-save.fields.icon'))
                ->icons(fn (): array => $options->icons())
                ->in(fn (): array => $options->icons()),
        ];
    }
}
