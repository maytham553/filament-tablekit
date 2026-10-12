@php
    use Elite\TableViews\Support\TableViewsOptions;
    use Filament\Support\Facades\FilamentIcon;
    use Illuminate\Support\Js;

    $enabled = $this->isTableViewMultiSortEnabled();

    if ($enabled) {
        $columns = $this->getTableViewSortableColumns();
        $sorts = $this->effectiveTableViewSorts();
        $used = array_column($sorts, 'column');
        $max = TableViewsOptions::resolve()->maxSorts();
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
                .fi-ta-header-cell[data-sort-priority="{{ $index + 1 }}"] .fi-ta-header-cell-label::after { content: "{{ $index + 1 }}"; margin-inline-start: 0.25rem; font-size: 0.7rem; font-weight: 600; opacity: 0.7; }
            @endforeach
        </style>
    @endif

    <x-filament::dropdown
        placement="bottom-start"
        shift
        teleport
        width="xs"
        class="fi-table-views-sort"
        wire:key="table-views-sort-{{ $this->getId() }}"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                color="gray"
                :icon="FilamentIcon::resolve('table-views::sort.open')"
                :label="__('table-views::sort.label')"
                :tooltip="__('table-views::sort.label')"
                :badge="count($sorts) ?: null"
                :disabled="$reordering"
                data-table-views-sort-trigger="true"
            />
        </x-slot>

        <div class="grid gap-y-3 p-4">
            <h4 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                {{ __('table-views::sort.heading') }}
            </h4>

            @if ($sorts !== [])
                <x-table-views::sortable
                    tag="div"
                    method="reorderTableViewSorts"
                    :enabled="$sortable"
                    class="grid gap-y-3"
                    wire:key="table-views-sort-rows-{{ $this->getId() }}"
                >
                    @foreach ($sorts as $index => $sort)
                        @php
                            $column = $columns[$sort['column']];
                            $name = $sort['column'];
                        @endphp

                        <div
                            class="flex items-center gap-x-1"
                            wire:key="table-views-sort-row-{{ $name }}"
                            data-sort-row="{{ $name }}"
                            @if ($sortable) x-sortable-item="{{ $name }}" @endif
                        >
                            @if ($sortable)
                                <x-table-views::sortable-handle :name="$column->getLabel()" />
                            @endif

                            <span class="w-5 shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400" title="{{ __('table-views::sort.priority', ['number' => $index + 1]) }}">{{ $index + 1 }}</span>

                            <span class="min-w-0 flex-1 truncate text-sm text-gray-950 dark:text-white">{{ $column->getLabel() }}</span>

                            <x-filament::icon-button
                                size="sm"
                                color="gray"
                                :icon="FilamentIcon::resolve($sort['direction'] === 'asc' ? 'table-views::sort.asc' : 'table-views::sort.desc')"
                                :label="__('table-views::sort.direction.'.$sort['direction'])"
                                :tooltip="__('table-views::sort.direction.'.$sort['direction'])"
                                wire:click="setTableViewSortDirection({{ Js::from($name) }}, '{{ $sort['direction'] === 'asc' ? 'desc' : 'asc' }}')"
                            />

                            <x-filament::icon-button
                                size="sm"
                                color="danger"
                                :icon="FilamentIcon::resolve('table-views::sort.remove')"
                                :label="__('table-views::sort.actions.remove')"
                                wire:click="removeTableViewSort({{ Js::from($name) }})"
                            />
                        </div>
                    @endforeach
                </x-table-views::sortable>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('table-views::sort.default_order') }}</p>
            @endif

            @if ($canAdd)
                <x-filament::input.wrapper>
                    <x-filament::input.select
                        :aria-label="__('table-views::sort.add')"
                        x-on:change="if ($event.target.value) { $wire.addTableViewSort($event.target.value); $event.target.value = '' }"
                    >
                        <option value="">{{ __('table-views::sort.add') }}</option>

                        @foreach ($columns as $name => $column)
                            @unless (in_array((string) $name, $used, true))
                                <option value="{{ $name }}">{{ $column->getLabel() }}</option>
                            @endunless
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            @endif

            @if ($limitReached)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('table-views::sort.limit', ['count' => $max]) }}</p>
            @endif

            @if ($sorts !== [])
                <div>
                    <x-filament::link tag="button" color="danger" size="sm" wire:click="clearTableViewSorts">
                        {{ __('table-views::sort.clear') }}
                    </x-filament::link>
                </div>
            @endif
        </div>
    </x-filament::dropdown>
@endif
