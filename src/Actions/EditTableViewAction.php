<?php

namespace TableKit\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use TableKit\Models\TableView;

class EditTableViewAction
{
    /**
     * The view id comes from client-writable action arguments, so it is resolved through $find (scoped query) in the
     * form, on mount and again on submit.
     *
     * @param  Closure(mixed): ?TableView  $find
     * @param  Closure(): void  $changed
     */
    public static function make(string $resourceKey, Closure $find, Closure $changed): Action
    {
        return Action::make('editTableView')
            ->modalHeading(__('tablekit::manager.edit.heading'))
            ->modalSubmitActionLabel(__('tablekit::manager.edit.submit'))
            ->modalWidth(MaxWidth::Medium)
            ->mountUsing(function (Action $action, ?Form $form) use ($find): void {
                $view = $find($action->getArguments()['viewId'] ?? null);

                if (! $view) {
                    $action->cancel();
                }

                $form?->fill([
                    'name' => $view->name,
                    'color' => $view->color,
                    'icon' => $view->icon,
                ]);
            })
            ->form(fn (Action $action): array => TableViewForm::fields(
                $resourceKey,
                fn (): int|string|null => $find($action->getArguments()['viewId'] ?? null)?->getKey(),
            ))
            ->action(function (Action $action, array $data) use ($find, $changed): void {
                $view = $find($action->getArguments()['viewId'] ?? null);

                if (! $view) {
                    return;
                }

                $view->update([
                    'name' => trim($data['name']),
                    'color' => $data['color'] ?? null,
                    'icon' => $data['icon'] ?? null,
                ]);

                $changed();

                Notification::make()->title(__('tablekit::manager.edit.saved'))->success()->send();
            });
    }
}
