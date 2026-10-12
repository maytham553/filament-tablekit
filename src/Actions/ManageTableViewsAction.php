<?php

namespace Elite\TableViews\Actions;

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
            ->label(__('table-views::manager.label'))
            ->icon(FilamentIcon::resolve('table-views::manager.open'))
            ->iconButton()
            ->color('gray')
            ->size(ActionSize::Small)
            ->tooltip(__('table-views::manager.label'))
            ->slideOver()
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(__('table-views::manager.heading'))
            ->modalDescription(__('table-views::manager.description'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('table-views::manager.close'))
            ->modalFooterActions(fn (Action $action): array => [
                $action->getModalCancelAction(),
                static::unrendered($edit),
                static::unrendered($delete),
            ])
            ->modalContent(fn () => view('table-views::manager-list'));
    }

    protected static function unrendered(Action $action): Action
    {
        return $action->label('')->extraAttributes(['hidden' => true, 'aria-hidden' => 'true', 'tabindex' => '-1']);
    }
}
