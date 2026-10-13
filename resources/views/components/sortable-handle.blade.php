@props(['name'])

@php
    use Filament\Support\Facades\FilamentIcon;
@endphp

<x-filament::icon-button
    size="sm"
    color="gray"
    :icon="FilamentIcon::resolve('tablekit::reorder.handle')"
    :label="__('tablekit::reorder.handle', ['name' => $name])"
    x-sortable-handle
    x-on:keydown.arrow-up.prevent="moveItem($el, -1)"
    x-on:keydown.arrow-down.prevent="moveItem($el, 1)"
    data-tablekit-reorder-handle
    style="cursor: grab"
/>
