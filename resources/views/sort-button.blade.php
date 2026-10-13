@php
    use TableKit\Support\TableKitOptions;
    use Filament\Support\Facades\FilamentIcon;
    use Illuminate\Support\Js;

    $enabled = $this->isTableViewMultiSortEnabled();

    if ($enabled) {
        $columns = $this->getTableViewSortableColumns();
        $sorts = $this->effectiveTableViewSorts();
        $used = array_column($sorts, 'column');
        $max = TableKitOptions::resolve()->maxSorts();
        $limitReached = count($sorts) >= $max;
        $canAdd = ! $limitReached && count($used) < count($columns);
        $reordering = $this->isTableReordering();
        $sortable = count($sorts) >= 2;
    }
@endphp

@if ($enabled)

    @if (count($sorts) >= 2)
        <style>
            @foreach ($sorts as $index => $sort)
                .fi-ta-header-cell[data-sort-priority="{{ $index + 1 }}"] .fi-ta-header-cell-sort-btn::after { content: "{{ $index + 1 }}"; font-size: 0.7rem; font-weight: 600; opacity: 0.7; }
            @endforeach
        </style>
    @endif

    @if ($reordering)
        <x-filament::icon-button
            color="gray"
            :icon="FilamentIcon::resolve('tablekit::sort.open')"
            :label="__('tablekit::sort.label')"
            :badge="count($sorts) ?: null"
            disabled
            data-tablekit-sort-trigger="true"
        />
    @else
        <x-filament::dropdown
            placement="bottom-start"
            shift
            teleport
            width="xs"
            :menu="false"
            class="fi-tablekit-sort"
            wire:key="tablekit-sort-{{ $this->getId() }}"
        >
            <x-slot name="trigger">
                <x-filament::icon-button
                    color="gray"
                    :icon="FilamentIcon::resolve('tablekit::sort.open')"
                    :label="__('tablekit::sort.label')"
                    :tooltip="__('tablekit::sort.label')"
                    :badge="count($sorts) ?: null"
                    :disabled="$reordering"
                    data-tablekit-sort-trigger="true"
                />
            </x-slot>

            <div style="display: grid; gap: 0.75rem; padding: 1rem">
                <h4 style="margin: 0; font-size: 1rem; font-weight: 600">
                    {{ __('tablekit::sort.heading') }}
                </h4>

                @if ($sorts !== [])
                    <x-tablekit::sortable
                        tag="div"
                        method="reorderTableViewSorts"
                        :enabled="$sortable"
                        style="display: grid; gap: 0.75rem"
                        wire:key="tablekit-sort-rows-{{ $this->getId() }}"
                    >
                        @foreach ($sorts as $index => $sort)
                            @php
                                $column = $columns[$sort['column']];
                                $name = $sort['column'];
                            @endphp

                            <div
                                style="display: flex; align-items: center; gap: 0.25rem"
                                wire:key="tablekit-sort-row-{{ $name }}"
                                data-sort-row="{{ $name }}"
                                @if ($sortable) x-sortable-item="{{ $name }}" @endif
                            >
                                @if ($sortable)
                                    <x-tablekit::sortable-handle :name="$column->getLabel()" />
                                @endif

                                <span style="width: 1.25rem; flex-shrink: 0; font-size: 0.75rem; font-weight: 500; color: var(--gray-500)" title="{{ __('tablekit::sort.priority', ['number' => $index + 1]) }}">{{ $index + 1 }}</span>

                                <span style="min-width: 0; flex: 1 1 0%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.875rem">{{ $column->getLabel() }}</span>

                                <x-filament::icon-button
                                    size="sm"
                                    color="gray"
                                    :icon="FilamentIcon::resolve($sort['direction'] === 'asc' ? 'tablekit::sort.asc' : 'tablekit::sort.desc')"
                                    :label="__('tablekit::sort.direction.'.$sort['direction'])"
                                    :tooltip="__('tablekit::sort.direction.'.$sort['direction'])"
                                    wire:click="setTableViewSortDirection({{ Js::from($name) }}, '{{ $sort['direction'] === 'asc' ? 'desc' : 'asc' }}')"
                                />

                                <x-filament::icon-button
                                    size="sm"
                                    color="danger"
                                    :icon="FilamentIcon::resolve('tablekit::sort.remove')"
                                    :label="__('tablekit::sort.actions.remove')"
                                    wire:click="removeTableViewSort({{ Js::from($name) }})"
                                />
                            </div>
                        @endforeach
                    </x-tablekit::sortable>
                @else
                    <p style="margin: 0; font-size: 0.875rem; color: var(--gray-500)">{{ __('tablekit::sort.default_order') }}</p>
                @endif

                @if ($canAdd)
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            :aria-label="__('tablekit::sort.add')"
                            x-on:change="if ($event.target.value) { $wire.addTableViewSort($event.target.value); $event.target.value = '' }"
                        >
                            <option value="">{{ __('tablekit::sort.add') }}</option>

                            @foreach ($columns as $name => $column)
                                @unless (in_array((string) $name, $used, true))
                                    <option value="{{ $name }}">{{ $column->getLabel() }}</option>
                                @endunless
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif

                @if ($limitReached)
                    <p style="margin: 0; font-size: 0.875rem; color: var(--gray-500)">{{ __('tablekit::sort.limit', ['count' => $max]) }}</p>
                @endif

                @if ($sorts !== [])
                    <div>
                        <x-filament::link tag="button" color="danger" size="sm" wire:click="clearTableViewSorts">
                            {{ __('tablekit::sort.clear') }}
                        </x-filament::link>
                    </div>
                @endif
            </div>
        </x-filament::dropdown>
    @endif
@endif
