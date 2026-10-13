<?php

namespace TableKit\Actions;

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
            ->label(__('tablekit::manager.label'))
            ->icon(FilamentIcon::resolve('tablekit::manager.open'))
            ->iconButton()
            ->color('gray')
            ->size(Size::Small)
            ->tooltip(__('tablekit::manager.label'))
            ->slideOver()
            ->unsavedChangesAlert(false)
            ->modalWidth(Width::Large)
            ->modalHeading(__('tablekit::manager.heading'))
            ->modalDescription(__('tablekit::manager.description'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('tablekit::manager.close'))
            ->registerModalActions([$edit, $delete])
            ->modalContent(fn () => view('tablekit::manager-list'));
    }
}
