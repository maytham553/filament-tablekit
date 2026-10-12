@props(['name'])

@php
    use Filament\Support\Facades\FilamentIcon;
@endphp

<x-filament::icon-button
    size="sm"
    color="gray"
    :icon="FilamentIcon::resolve('table-views::reorder.handle')"
    :label="__('table-views::reorder.handle', ['name' => $name])"
    x-sortable-handle
    x-on:keydown.arrow-up.prevent="moveItem($el, -1)"
    x-on:keydown.arrow-down.prevent="moveItem($el, 1)"
    data-table-views-reorder-handle
    class="cursor-grab active:cursor-grabbing"
/>
