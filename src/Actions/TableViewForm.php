<?php

namespace Elite\TableViews\Actions;

use Closure;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Support\TableViewsOptions;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

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
            Select::make('color')
                ->label(__('table-views::quick-save.fields.color'))
                ->options(fn (): array => collect($options->colors())
                    ->mapWithKeys(fn (string $color): array => [$color => static::colorLabel($color)])
                    ->all())
                ->in(fn (): array => $options->colors())
                ->required()
                ->native(false),
            Select::make('icon')
                ->label(__('table-views::quick-save.fields.icon'))
                ->options(fn (): array => collect($options->icons())
                    ->mapWithKeys(fn (string $icon): array => [$icon => __("table-views::icons.{$icon}")])
                    ->all())
                ->in(fn (): array => $options->icons())
                ->placeholder(__('table-views::quick-save.fields.no_icon'))
                ->native(false),
        ];
    }

    protected static function colorLabel(string $color): string
    {
        $key = "table-views::colors.{$color}";
        $label = __($key);

        return $label === $key ? ucfirst($color) : $label;
    }
}
