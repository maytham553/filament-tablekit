<?php

use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

function defaultButton(string $html): string
{
    preg_match('/<button[^>]*data-default="true"[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

function viewButton(string $html, int $id): string
{
    preg_match('/<button[^>]*wire:key="table-view-'.$id.'"[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

it('highlights the default button when no view is active', function () {
    $view = storeView($this->user->id, ['search' => 'ORD-01']);

    $html = Livewire::test(ListOrders::class)->html();

    expect(defaultButton($html))->toContain('aria-pressed="true"')->toContain('box-shadow')
        ->and(viewButton($html, $view->id))->toContain('aria-pressed="false"')->not->toContain('box-shadow');
});

it('moves the highlight to the applied view and back on default', function () {
    $view = storeView($this->user->id, ['search' => 'ORD-01']);

    $component = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);
    $html = $component->html();

    expect(defaultButton($html))->toContain('aria-pressed="false"')->not->toContain('box-shadow')
        ->and(viewButton($html, $view->id))->toContain('aria-pressed="true"')->toContain('data-active="true"')->toContain('box-shadow');

    $html = $component->call('applyDefaultTableView')->html();

    expect(defaultButton($html))->toContain('aria-pressed="true"')
        ->and(viewButton($html, $view->id))->toContain('aria-pressed="false"');
});

it('marks only the active view as modified, with text a screen reader can read', function () {
    $view = storeView($this->user->id, ['search' => 'ORD-01']);

    $component = Livewire::test(ListOrders::class)->call('applyTableView', $view->id);

    expect($component->html())->not->toContain(__('table-views::favorites-bar.modified'));

    $html = $component->set('tableSearch', 'ORD-02')->html();

    expect($html)->toContain('<span class="sr-only">'.__('table-views::favorites-bar.modified').'</span>')
        ->and(viewButton($html, $view->id))->toContain('title="'.__('table-views::favorites-bar.modified').'"');
});

it('uses a color-independent ring, so an active gray view is still visible', function () {
    $view = storeView($this->user->id, [], ['color' => 'gray']);

    $html = Livewire::test(ListOrders::class)->call('applyTableView', $view->id)->html();

    expect(viewButton($html, $view->id))->toContain('box-shadow: 0 0 0 2px var(--primary-500)');
});
