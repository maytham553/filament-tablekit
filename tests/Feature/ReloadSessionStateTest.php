<?php

use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersPersistent;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrdersUnpersistedColumns;
use Livewire\Livewire;

beforeEach(function () {
    config(['table-views.persist_active_view_in_session' => true]);

    $this->user = $this->signIn();
    $this->seedOrders();

    $this->shownNotes = fn (string $page = ListOrders::class) => storeView($this->user->id, [
        'toggledColumns' => ['notes' => true, 'ordered_at' => true],
        'sort' => ['column' => 'amount', 'direction' => 'desc'],
    ], ['name' => 'Notes', 'resource' => $page]);
});

describe('a remembered view applied on load keeps what the session persisted on top of it', function () {
    it('keeps the toggled columns the user changed after applying the view', function () {
        $view = ($this->shownNotes)();

        $first = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);
        setToggles($first, ['ordered_at' => false]);

        $again = Livewire::test(ListOrders::class);

        expect($again->get('activeTableViewId'))->toBe($view->id)
            ->and(toggles($again))->toBe(['notes' => true, 'ordered_at' => false])
            ->and(callProtected($again, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps the sort the user changed when the table persists sort in the session', function () {
        $view = ($this->shownNotes)(ListOrdersPersistent::class);

        $first = Livewire::test(ListOrdersPersistent::class)->call('applyTableView', $view->id)->call('sortTable', 'reference');

        $again = Livewire::test(ListOrdersPersistent::class);

        expect($again->get('activeTableViewId'))->toBe($view->id)
            ->and($again->get('tableSort'))->toBe('reference:asc')
            ->and($again->get('tableSort'))->toBe($first->get('tableSort'));
    });

    it('applies the view in full when the session holds nothing but the defaults', function () {
        $view = ($this->shownNotes)();

        Livewire::test(ListOrders::class)->call('applyTableView', $view->id);

        $again = Livewire::test(ListOrders::class);

        expect(toggles($again))->toBe(['notes' => true, 'ordered_at' => true])
            ->and($again->get('tableSort'))->toBe('amount:desc')
            ->and(callProtected($again, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('applies the view toggles over the defaults when the table does not persist columns', function () {
        $view = ($this->shownNotes)(ListOrdersUnpersistedColumns::class);

        $first = Livewire::test(ListOrdersUnpersistedColumns::class)->call('applyTableView', $view->id);
        setToggles($first, ['ordered_at' => false]);

        $again = Livewire::test(ListOrdersUnpersistedColumns::class);

        expect(toggles($again))->toBe(['notes' => true, 'ordered_at' => true]);
    });
});

it('replaces everything when a view is applied by click', function () {
    $view = ($this->shownNotes)(ListOrdersPersistent::class);

    $component = Livewire::test(ListOrdersPersistent::class)->call('sortTable', 'reference');
    setToggles($component, ['notes' => false, 'ordered_at' => false]);

    $component->call('applyTableView', $view->id);

    expect(toggles($component))->toBe(['notes' => true, 'ordered_at' => true])
        ->and($component->get('tableSort'))->toBe('amount:desc')
        ->and(callProtected($component, 'isActiveTableViewModified'))->toBeFalse();
});
