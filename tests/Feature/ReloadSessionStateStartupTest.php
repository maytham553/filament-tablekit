<?php

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use TableKit\Presets\PresetTableView;
use TableKit\Tests\Fixtures\Pages\ListOrdersPersistent;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;

function reloadWithUrl(Testable $mounted, Closure $url, string $page = ListOrdersWithPresets::class): Testable
{
    return Livewire::withQueryParams($url($mounted))->test($page);
}

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();

    $this->notesView = storeView($this->user->id, [
        'toggledColumns' => ['notes' => true, 'ordered_at' => false],
        'sort' => ['column' => 'amount', 'direction' => 'desc'],
    ], ['name' => 'Notes', 'resource' => ListOrdersWithPresets::class]);

    $this->makeDefault = fn (string $type, string $key, string $page = ListOrdersWithPresets::class) => storePreference($this->user->id, $type, $key, ['is_default' => true, 'resource' => $page]);
});

describe('a url reload keeps what the session persisted on top of the view', function () {
    it('keeps the toggled columns the user changed after applying a view', function () {
        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->notesView->id);
        setToggles($mounted, ['notes' => true, 'ordered_at' => true]);

        $reloaded = reloadWithUrl($mounted, $url);

        expect($reloaded->get('activeTableViewId'))->toBe($this->notesView->id)
            ->and(toggles($reloaded))->toBe(['notes' => true, 'ordered_at' => true])
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('uses the view\'s own columns when the session stored none', function () {
        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTableView', $this->notesView->id);

        session()->flush();
        $this->actingAs($this->user);

        $reloaded = reloadWithUrl($mounted, $url);

        expect(toggles($reloaded))->toBe(['notes' => true, 'ordered_at' => false])
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse();
    });

    it('does the same for a preset', function () {
        presets(fn () => ['notes' => PresetTableView::make()->label('Notes')->favorite()->defaultToggledColumns(['notes'])]);

        $mounted = Livewire::test(ListOrdersWithPresets::class);
        $url = browserUrl($mounted);
        $mounted->call('applyTablePresetView', 'notes');

        session()->flush();
        $this->actingAs($this->user);

        $reloaded = reloadWithUrl($mounted, $url);

        expect($reloaded->get('activeTablePresetKey'))->toBe('notes')
            ->and(toggles($reloaded))->toBe(['notes' => true, 'ordered_at' => false])
            ->and(callProtected($reloaded, 'isActiveTableViewModified'))->toBeFalse();
    });
});

describe('a fresh load that applies a view by itself keeps the session state', function () {
    it('keeps the toggles over the managed default view', function () {
        ($this->makeDefault)('user', (string) $this->notesView->id);
        config(['tablekit.managed_default_views' => true]);

        $first = Livewire::test(ListOrdersWithPresets::class);

        expect(toggles($first))->toBe(['notes' => true, 'ordered_at' => false]);

        setToggles($first, ['notes' => true, 'ordered_at' => true]);

        $again = Livewire::test(ListOrdersWithPresets::class);

        expect($again->get('activeTableViewId'))->toBe($this->notesView->id)
            ->and(toggles($again))->toBe(['notes' => true, 'ordered_at' => true])
            ->and(callProtected($again, 'isActiveTableViewModified'))->toBeTrue();
    });

    it('keeps the toggles over the developer default preset', function () {
        presets(fn () => ['dev' => PresetTableView::make()->label('Dev')->default()->defaultToggledColumns(['notes'])]);

        $first = Livewire::test(ListOrdersWithPresets::class);

        expect($first->get('activeTablePresetKey'))->toBe('dev')->and(toggles($first))->toBe(['notes' => true, 'ordered_at' => false]);

        setToggles($first, ['notes' => true, 'ordered_at' => true]);

        $again = Livewire::test(ListOrdersWithPresets::class);

        expect($again->get('activeTablePresetKey'))->toBe('dev')->and(toggles($again))->toBe(['notes' => true, 'ordered_at' => true]);
    });

    it('keeps the toggles over the remembered preset', function () {
        config(['tablekit.persist_active_view_in_session' => true]);
        presets(fn () => ['notes' => PresetTableView::make()->label('Notes')->favorite()->defaultToggledColumns(['notes'])]);

        $first = Livewire::test(ListOrdersWithPresets::class)->call('applyTablePresetView', 'notes');
        setToggles($first, ['notes' => true, 'ordered_at' => true]);

        $again = Livewire::test(ListOrdersWithPresets::class);

        expect($again->get('activeTablePresetKey'))->toBe('notes')->and(toggles($again))->toBe(['notes' => true, 'ordered_at' => true]);
    });

    it('keeps a persisted sort over the startup view', function () {
        config(['tablekit.managed_default_views' => true]);

        $default = storeView($this->user->id, ['sort' => ['column' => 'reference', 'direction' => 'asc']], ['resource' => ListOrdersPersistent::class]);
        storePreference($this->user->id, 'user', (string) $default->id, ['is_default' => true, 'resource' => ListOrdersPersistent::class]);

        $first = Livewire::test(ListOrdersPersistent::class);

        expect($first->get('tableSort'))->toBe('reference:asc');

        $first->call('sortTable', 'amount', 'desc');

        $again = Livewire::test(ListOrdersPersistent::class);

        expect($again->get('activeTableViewId'))->toBe($default->id)->and($again->get('tableSort'))->toBe('amount:desc');
    });

    it('applies the startup view in full when the session stored nothing', function () {
        ($this->makeDefault)('user', (string) $this->notesView->id);
        config(['tablekit.managed_default_views' => true]);

        $mounted = Livewire::test(ListOrdersWithPresets::class);

        expect(toggles($mounted))->toBe(['notes' => true, 'ordered_at' => false])
            ->and($mounted->get('tableSort'))->toBe('amount:desc')
            ->and(callProtected($mounted, 'isActiveTableViewModified'))->toBeFalse();
    });
});

it('still replaces everything when a preset is applied by click', function () {
    presets(fn () => ['notes' => PresetTableView::make()->label('Notes')->favorite()->defaultToggledColumns(['notes'])->defaultSort('reference')]);

    $component = Livewire::test(ListOrdersWithPresets::class)->call('sortTable', 'amount', 'desc');
    setToggles($component, ['notes' => true, 'ordered_at' => true]);

    $component->call('applyTablePresetView', 'notes');

    expect(toggles($component))->toBe(['notes' => true, 'ordered_at' => false])
        ->and($component->get('tableSort'))->toBe('reference:asc');
});
