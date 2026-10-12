<?php

namespace Elite\TableViews\Actions;

use Closure;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Support\TableViewsOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * Name, color and icon fields of the Quick Save form.
 */
class TableViewForm
{
    /**
     * @return array<int, Component>
     */
    public static function fields(string $resourceKey): array
    {
        $options = TableViewsOptions::resolve();

        return [
            TextInput::make('name')
                ->label(__('table-views::quick-save.fields.name'))
                ->required()
                ->maxLength($options->maxNameLength())
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($resourceKey): void {
                    if (is_string($value) && TableView::nameTaken($value, $resourceKey)) {
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
