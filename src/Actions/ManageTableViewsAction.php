<?php

namespace Elite\TableViews\Actions;

use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentIcon;

class ManageTableViewsAction
{
    public const NAME = 'manageTableViews';

    /**
     * Edit and delete are registered as modal actions of the slide over: Filament only resolves a nested mounted
     * action through its parent, and the rows of the list mount them by name.
     */
    public static function make(Action $edit, Action $delete): Action
    {
        return Action::make(self::NAME)
            ->label(__('table-views::manager.label'))
            ->icon(FilamentIcon::resolve('table-views::manager.open'))
            ->iconButton()
            ->color('gray')
            ->size(Size::Small)
            ->tooltip(__('table-views::manager.label'))
            ->slideOver()
            ->unsavedChangesAlert(false)
            ->modalWidth(Width::Large)
            ->modalHeading(__('table-views::manager.heading'))
            ->modalDescription(__('table-views::manager.description'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('table-views::manager.close'))
            ->registerModalActions([$edit, $delete])
            ->modalContent(fn () => view('table-views::manager-list'));
    }
}
