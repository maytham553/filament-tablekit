<?php

namespace Elite\TableViews\Actions;

use Closure;
use Elite\TableViews\Models\TableView;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DeleteTableViewAction
{
    /**
     * @param  Closure(mixed): ?TableView  $find  scoped lookup, the view id comes from client-writable action arguments
     * @param  Closure(TableView): void  $deleted
     */
    public static function make(Closure $find, Closure $deleted): Action
    {
        return Action::make('deleteTableView')
            ->color('danger')
            ->requiresConfirmation()
            ->unsavedChangesAlert(false)
            ->modalHeading(__('table-views::manager.delete.heading'))
            ->modalDescription(__('table-views::manager.delete.description'))
            ->modalSubmitActionLabel(__('table-views::manager.delete.submit'))
            ->mountUsing(function (Action $action) use ($find): void {
                if (! $find($action->getArguments()['viewId'] ?? null)) {
                    $action->cancel();
                }
            })
            ->action(function (Action $action) use ($find, $deleted): void {
                $view = $find($action->getArguments()['viewId'] ?? null);

                if (! $view) {
                    return;
                }

                $view->delete();

                $deleted($view);

                Notification::make()->title(__('table-views::manager.delete.deleted'))->success()->send();
            });
    }
}
