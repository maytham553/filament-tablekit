<?php

namespace Elite\TableViews\Actions;

use Closure;
use Elite\TableViews\Models\TableView;
use Elite\TableViews\Support\TableViewsOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentIcon;

class QuickSaveTableViewAction
{
    /**
     * @param  Closure(array<string, mixed>): TableView  $save
     */
    public static function make(string $resourceKey, Closure $save): Action
    {
        $options = TableViewsOptions::resolve();

        return Action::make('quickSaveTableView')
            ->label(__('table-views::quick-save.label'))
            ->icon(FilamentIcon::resolve('table-views::quick-save'))
            ->color('gray')
            ->size(Size::Small)
            ->outlined()
            ->modalHeading(__('table-views::quick-save.heading'))
            ->modalDescription(__('table-views::quick-save.description'))
            ->modalSubmitActionLabel(__('table-views::quick-save.submit'))
            ->modalWidth(Width::Medium)
            ->slideOver($options->isQuickSaveSlideOver())
            ->fillForm([
                'name' => null,
                'color' => $options->colors()[0] ?? 'primary',
                'icon' => null,
                'is_favorite' => true,
            ])
            ->schema([
                ...TableViewForm::fields($resourceKey),
                Toggle::make('is_favorite')
                    ->label(__('table-views::quick-save.fields.is_favorite')),
            ])
            ->action(function (array $data) use ($save): void {
                $save($data);

                Notification::make()
                    ->title(__('table-views::quick-save.saved'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Shown instead of the real action while a preset with a hidden query is active: that query would not be part of the saved view.
     */
    public static function blocked(): Action
    {
        return Action::make('quickSaveTableView')
            ->label(__('table-views::quick-save.label'))
            ->icon(FilamentIcon::resolve('table-views::quick-save'))
            ->color('gray')
            ->size(Size::Small)
            ->outlined()
            ->action(function (): void {
                Notification::make()
                    ->title(__('table-views::quick-save.preset_with_query_title'))
                    ->body(__('table-views::quick-save.preset_with_query_body'))
                    ->warning()
                    ->send();
            });
    }
}
