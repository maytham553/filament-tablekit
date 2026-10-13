@props([
    'method',
    'arguments' => [],
    'tag' => 'ul',
    'enabled' => true,
    'scopeOnly' => false,
])

@php
    use Illuminate\Support\Js;
@endphp

@if ($enabled)
    <{{ $tag }}
        {{ $attributes->merge(['style' => 'margin: 0; padding: 0; list-style: none']) }}
        x-data="{
            saveOrder(order) {
                return $wire.$call({{ Js::from($method) }}, ...{{ Js::from($arguments) }}, order)
            },
            moveItem(handle, step) {
                const item = handle.closest('[x-sortable-item]')
                const list = item.parentElement
                const key = item.getAttribute('x-sortable-item')
                const order = list.sortable.toArray()
                const from = order.indexOf(key)
                const to = from + step

                if (from < 0 || to < 0 || to >= order.length) {
                    return
                }

                order.splice(to, 0, order.splice(from, 1)[0])

                this.saveOrder(order).then(() => this.$nextTick(() => requestAnimationFrame(() => {
                    Array.from(list.children)
                        .find((child) => child.getAttribute('x-sortable-item') === key)
                        ?.querySelector('[x-sortable-handle]')
                        ?.focus()
                })))
            },
        }"
        @unless ($scopeOnly)
            x-sortable
            x-on:end.stop="$event.oldIndex !== $event.newIndex && saveOrder($el.sortable.toArray())"
        @endunless
        data-tablekit-sortable
    >
        {{ $slot }}
    </{{ $tag }}>
@else
    <{{ $tag }} {{ $attributes->merge(['style' => 'margin: 0; padding: 0; list-style: none']) }}>
        {{ $slot }}
    </{{ $tag }}>
@endif
