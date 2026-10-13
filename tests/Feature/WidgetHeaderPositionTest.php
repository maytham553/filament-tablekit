<?php

use Livewire\Livewire;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Widgets\OrdersWidget;
use TableKit\Tests\Fixtures\Widgets\OrdersWidgetWithHeading;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->seedOrders();
});

it('renders the bar of a table widget below its heading', function () {
    Livewire::test(OrdersWidgetWithHeading::class)
        ->assertSeeInOrder(['Latest orders', 'Default', 'Paid only', 'Save view']);
});

it('renders the bar once on a widget and once on a page', function () {
    expect(substr_count(Livewire::test(OrdersWidgetWithHeading::class)->html(), 'fi-tablekit-bar'))->toBe(1)
        ->and(substr_count(Livewire::test(OrdersWidget::class)->html(), 'fi-tablekit-bar'))->toBe(1)
        ->and(substr_count(Livewire::test(ListOrders::class)->html(), 'fi-tablekit-bar'))->toBe(1);
});

it('applies a hidden query preset on a widget', function () {
    Livewire::test(OrdersWidgetWithHeading::class)
        ->call('applyTablePresetView', 'paid')
        ->assertCountTableRecords(Order::where('status', 'paid')->count())
        ->call('applyDefaultTableView')
        ->assertCountTableRecords(Order::count());
});

it('keeps the bar above the content on a page', function () {
    $html = Livewire::test(ListOrders::class)->html();

    expect(strpos($html, 'fi-tablekit-bar'))->toBeLessThan(strpos($html, 'fi-ta-content'));
});
