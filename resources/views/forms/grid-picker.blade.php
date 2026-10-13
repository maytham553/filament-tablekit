@php
    use Filament\Support\Facades\FilamentIcon;
    use Illuminate\Support\Js;

    $items = $getItems();
    $kind = $getKind();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $searchable = $isSearchable();
    $state = $getState();
    $selected = is_string($state) || is_int($state) ? (string) $state : '';
    $values = array_column($items, 'value');
    $tabStop = in_array($selected, $values, true) ? $selected : ($values[0] ?? '');
    $entries = array_map(fn (array $item): array => ['v' => $item['value'], 's' => $item['search']], $items);
    $checkIcon = FilamentIcon::resolve('table-views::manager.active');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @once
        <style>
            .tv-picker {
                --tv-ring: rgb(var(--primary-600));
                --tv-gap: #fff;
                --tv-border: rgb(var(--gray-200));
                --tv-hover: rgb(var(--gray-100));
                --tv-soft: rgb(var(--primary-50));
                --tv-icon: rgb(var(--gray-600));
                --tv-muted: rgb(var(--gray-500));
            }
            .dark .tv-picker {
                --tv-ring: rgb(var(--primary-400));
                --tv-gap: rgb(var(--gray-900));
                --tv-border: rgb(var(--gray-700));
                --tv-hover: rgb(255 255 255 / 0.08);
                --tv-soft: rgb(var(--primary-400) / 0.15);
                --tv-icon: rgb(var(--gray-300));
                --tv-muted: rgb(var(--gray-400));
            }
            .tv-picker__grid { display: grid; gap: 0.5rem; }
            .tv-picker--color .tv-picker__grid { grid-template-columns: repeat(auto-fill, minmax(2rem, 1fr)); padding: 0.25rem; justify-items: center; }
            .tv-picker--icon .tv-picker__grid { grid-template-columns: repeat(auto-fill, minmax(2.5rem, 1fr)); justify-items: center; }
            .tv-picker__scroll { max-height: 14rem; overflow-y: auto; padding: 0.5rem; border: 1px solid var(--tv-border); border-radius: 0.5rem; }
            .tv-picker__search { margin-block-end: 0.5rem; }
            .tv-picker__empty { padding: 1rem; text-align: center; font-size: 0.875rem; color: var(--tv-muted); }
            .tv-cell { position: relative; display: inline-flex; align-items: center; justify-content: center; padding: 0; cursor: pointer; line-height: 1; }
            .tv-cell:disabled { cursor: not-allowed; opacity: 0.5; }
            .tv-cell:focus-visible { outline: 2px solid var(--tv-ring); outline-offset: 2px; }
            .tv-cell--color { width: 2rem; height: 2rem; border-radius: 9999px; border: 0; background-color: rgb(var(--c-500)); transition: box-shadow 0.1s; }
            .tv-cell--color:hover { box-shadow: 0 0 0 2px var(--tv-gap), 0 0 0 4px var(--tv-border); }
            .tv-cell--color.tv-selected { box-shadow: 0 0 0 2px var(--tv-gap), 0 0 0 4px var(--tv-ring); }
            .tv-cell--icon { width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; border: 1px solid transparent; background: transparent; color: var(--tv-icon); }
            .tv-cell--icon:hover { background: var(--tv-hover); }
            .tv-cell--icon.tv-selected { background: var(--tv-soft); border-color: var(--tv-ring); color: var(--tv-ring); }
            .tv-cell--icon svg { width: 1.25rem; height: 1.25rem; }
            .tv-check { width: 1rem; height: 1rem; color: #fff; filter: drop-shadow(0 0 1px rgb(0 0 0 / 0.6)); }
            .tv-cell:not(.tv-selected) .tv-check { display: none; }
            .tv-none { width: 1.25rem; height: 1.25rem; border-radius: 9999px; border: 2px dashed currentColor; opacity: 0.6; }
        </style>
    @endonce

    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            search: '',
            items: {{ Js::from($entries) }},
            disabled: {{ Js::from($isDisabled) }},
            current() { return this.state ?? '' },
            isSelected(value) { return this.current() === value },
            matches(value) {
                const query = this.search.trim().toLowerCase()

                return query === '' || this.items.some((item) => item.v === value && item.s.includes(query))
            },
            visible() { return this.items.filter((item) => this.matches(item.v)).map((item) => item.v) },
            tabindex(value) {
                const visible = this.visible()
                const stop = visible.includes(this.current()) ? this.current() : visible[0]

                return value === stop ? 0 : -1
            },
            choose(value) {
                if (this.disabled) return

                this.state = value === '' ? null : value
            },
            cell(value) {
                return [...this.$refs.grid.querySelectorAll('[data-value]')].find((el) => el.dataset.value === value)
            },
            columns() {
                const cells = [...this.$refs.grid.querySelectorAll('[data-value]')].filter((el) => el.offsetParent !== null)

                return cells.length ? Math.max(1, cells.filter((el) => el.offsetTop === cells[0].offsetTop).length) : 1
            },
            onKey(event) {
                if (this.disabled || ! ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return

                const visible = this.visible()

                if (! visible.length) return

                const rtl = getComputedStyle(this.$refs.grid).direction === 'rtl'
                const from = event.target.closest('[data-value]')?.dataset.value ?? this.current()
                const index = Math.max(0, visible.indexOf(from))
                let next = index

                if (event.key === 'ArrowRight') next = index + (rtl ? -1 : 1)
                if (event.key === 'ArrowLeft') next = index + (rtl ? 1 : -1)
                if (event.key === 'ArrowDown') next = index + this.columns()
                if (event.key === 'ArrowUp') next = index - this.columns()
                if (event.key === 'Home') next = 0
                if (event.key === 'End') next = visible.length - 1

                next = Math.min(visible.length - 1, Math.max(0, next))

                event.preventDefault()
                this.choose(visible[next])
                this.cell(visible[next])?.focus()
            },
            fromSearch() {
                const visible = this.visible()
                const target = visible.includes(this.current()) ? this.current() : visible[0]

                if (target !== undefined) this.cell(target)?.focus()
            },
        }"
        class="tv-picker tv-picker--{{ $kind }}"
        data-tv-picker="{{ $kind }}"
    >
        @if ($searchable)
            <div class="tv-picker__search">
                <x-filament::input.wrapper :disabled="$isDisabled">
                    <x-filament::input
                        type="search"
                        x-model="search"
                        x-on:keydown.enter.prevent
                        x-on:keydown.down.prevent="fromSearch()"
                        :disabled="$isDisabled"
                        :placeholder="__('table-views::pickers.search')"
                        :aria-label="__('table-views::pickers.search')"
                        data-tv-search
                        autocomplete="off"
                    />
                </x-filament::input.wrapper>
            </div>
        @endif

        <div @class(['tv-picker__scroll' => $kind === 'icon'])>
            <div
                x-ref="grid"
                role="radiogroup"
                aria-label="{{ $getLabel() }}"
                x-on:keydown="onKey($event)"
                class="tv-picker__grid"
            >
                @foreach ($items as $item)
                    @php($isSelected = $item['value'] === $selected)

                    <button
                        type="button"
                        role="radio"
                        wire:key="tv-picker-{{ $statePath }}-{{ $item['value'] === '' ? 'none' : $item['value'] }}"
                        data-value="{{ $item['value'] }}"
                        title="{{ $item['label'] }}"
                        aria-label="{{ $item['label'] }}"
                        aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                        tabindex="{{ $item['value'] === $tabStop ? 0 : -1 }}"
                        @disabled($isDisabled)
                        @if ($item['style']) style="{{ $item['style'] }}" @endif
                        @class(['tv-cell', "tv-cell--{$kind}", 'tv-selected' => $isSelected])
                        x-bind:class="{ 'tv-selected': isSelected($el.dataset.value) }"
                        x-bind:aria-checked="isSelected($el.dataset.value) ? 'true' : 'false'"
                        x-bind:tabindex="tabindex($el.dataset.value)"
                        x-show="matches($el.dataset.value)"
                        x-on:click="choose($el.dataset.value)"
                    >
                        @if ($kind === 'color')
                            @if ($checkIcon)
                                <x-filament::icon :icon="$checkIcon" class="tv-check" />
                            @endif
                        @elseif ($item['none'])
                            <span class="tv-none" aria-hidden="true"></span>
                        @elseif ($item['icon'])
                            <x-filament::icon :icon="$item['icon']" />
                        @endif
                    </button>
                @endforeach
            </div>

            @if ($searchable)
                <p class="tv-picker__empty" style="display: none" x-show="visible().length === 0">
                    {{ __('table-views::pickers.no_results') }}
                </p>
            @endif
        </div>
    </div>
</x-dynamic-component>
