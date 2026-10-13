<?php

namespace TableKit\Actions;

use Filament\Actions\Action;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Facades\FilamentIcon;

class ManageTableViewsAction
{
    public const NAME = 'manageTableViews';

    /**
     * Edit and delete are registered as modal actions of the slide over: Filament only resolves a nested mounted
     * action through its parent, and the rows of the list mount them by name. A hidden() action cannot be mounted,
     * so they stay "visible" and are kept out of the footer with the hidden attribute.
     */
    public static function make(Action $edit, Action $delete): Action
    {
        return Action::make(self::NAME)
            ->label(__('tablekit::manager.label'))
            ->icon(FilamentIcon::resolve('tablekit::manager.open'))
            ->iconButton()
            ->color('gray')
            ->size(ActionSize::Small)
            ->tooltip(__('tablekit::manager.label'))
            ->slideOver()
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(__('tablekit::manager.heading'))
            ->modalDescription(__('tablekit::manager.description'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('tablekit::manager.close'))
            ->modalFooterActions(fn (Action $action): array => [
                $action->getModalCancelAction(),
                static::unrendered($edit),
                static::unrendered($delete),
            ])
            ->modalContent(fn () => view('tablekit::manager-list'));
    }

    protected static function unrendered(Action $action): Action
    {
        return $action->label('')->extraAttributes(['hidden' => true, 'aria-hidden' => 'true', 'tabindex' => '-1']);
    }
}
