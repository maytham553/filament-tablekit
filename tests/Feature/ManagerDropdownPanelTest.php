<?php

use Livewire\Livewire;
use TableKit\Tests\Fixtures\Pages\ListOrders;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders(3);
    storeView($this->user->id, ['search' => 'one'], ['name' => 'With details']);
});

function managerPanelHtml(): string
{
    return Livewire::test(ListOrders::class)->call('openTableViewsManager')->html();
}

it('renders the dropdown panel with a fixed position so the table card cannot clip it', function () {
    $html = managerPanelHtml();

    preg_match('/<div[^>]*x-float[^>]*fi-dropdown-panel[^>]*>|<div[^>]*fi-dropdown-panel[^>]*x-float[^>]*>/s', $html, $matches);

    expect($html)->toContain('fi-tablekit-manager"')
        ->and($matches)->not->toBeEmpty()
        ->and($matches[0])->toContain('.placement.bottom-end')
        ->and($matches[0])->toContain('.teleport')
        ->and($matches[0])->toContain('.flip')
        ->and($matches[0])->toContain('.shift');
});

it('caps the list height and scrolls inside the panel', function () {
    $html = managerPanelHtml();

    expect($html)->toMatch('/fi-dropdown-panel[^>]*overflow-y-auto/s')
        ->and($html)->toMatch('/style="[^"]*max-height: 24rem/s');
});

it('keeps the rows and the close-on-click handlers inside the panel', function () {
    $html = managerPanelHtml();

    $panel = substr($html, strpos($html, 'fi-dropdown-panel'));

    expect($panel)->toContain('With details')
        ->and($panel)->toContain('x-on:click="close($event)"');
});

it('does not use the teleported dropdown in slide-over mode', function () {
    config(['tablekit.view_manager.slide_over' => true]);

    expect(Livewire::test(ListOrders::class)->html())->not->toContain('fi-tablekit-manager"');
});
