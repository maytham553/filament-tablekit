@php
    use Elite\TableViews\TableViewsServiceProvider;
    use Filament\Support\Facades\FilamentIcon;
    use Illuminate\Support\Js;

    $enabled = isset($this) && TableViewsServiceProvider::usesTableViews($this::class) && $this->isTableViewQuickFiltersEnabled();

    if ($enabled) {
        $table = $this->getTable();
        $searchIndicators = $this->getTableViewSearchIndicators();
        $chips = $this->getTableViewQuickFilterChips();
        $deferred = $table->hasDeferredFilters();
        $canClear = collect($searchIndicators)->contains(fn ($indicator): bool => $indicator->isRemovable())
            || collect($chips)->contains(fn (array $chip): bool => $chip['remove'] !== null);
    }
@endphp

@if ($enabled && ($searchIndicators !== [] || $chips !== []))
    <div
        class="fi-table-views-quick-filters flex items-start justify-between gap-x-3 bg-gray-50 px-3 py-2 dark:bg-white/5 sm:px-6"
        wire:key="table-views-quick-filters-{{ $this->getId() }}"
    >
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach ($searchIndicators as $index => $indicator)
                <x-filament::badge :color="$indicator->getColor()" wire:key="table-views-search-chip-{{ $index }}">
                    {{ $indicator->getLabel() }}

                    @if ($indicator->isRemovable())
                        <x-slot
                            name="deleteButton"
                            :label="__('table-views::quick-filters.actions.remove')"
                            wire:click="{{ $indicator->getRemoveLivewireClickHandler() }}"
                        ></x-slot>
                    @endif
                </x-filament::badge>
            @endforeach

            @foreach ($chips as $chip)
                <x-filament::dropdown
                    placement="bottom-start"
                    shift
                    teleport
                    width="sm"
                    class="fi-table-views-quick-filter"
                    :data-quick-filter="$chip['name']"
                    x-on:click.outside="if ($wire.tableViewOpenQuickFilter === {{ Js::from($chip['name']) }}) { $wire.closeTableViewQuickFilter({{ Js::from($chip['name']) }}) }"
                    wire:key="table-views-quick-filter-{{ $this->getId() }}-{{ $chip['name'] }}"
                >
                    <x-slot name="trigger">
                        <x-filament::badge
                            :color="$chip['color']"
                            :loading-indicator="false"
                            :data-active="$chip['active'] ? 'true' : 'false'"
                            role="button"
                            tabindex="0"
                            x-on:click="$wire.openTableViewQuickFilter({{ Js::from($chip['name']) }})"
                            x-on:keydown.enter.prevent="$wire.openTableViewQuickFilter({{ Js::from($chip['name']) }}); toggle($event)"
                            x-on:keydown.space.prevent="$wire.openTableViewQuickFilter({{ Js::from($chip['name']) }}); toggle($event)"
                            :aria-label="__('table-views::quick-filters.edit', ['filter' => strip_tags((string) ($chip['active'] ? $chip['label'] : $chip['filter']->getLabel()))])"
                        >
                            {{ $chip['label'] }}
                            <x-filament::icon
                                :icon="FilamentIcon::resolve('table-views::quick-filters.chevron')"
                                class="inline h-3.5 w-3.5 align-text-bottom opacity-60"
                            />

                            @if ($chip['remove'] !== null)
                                <x-slot
                                    name="deleteButton"
                                    :label="__('table-views::quick-filters.actions.remove')"
                                    x-on:click.stop=""
                                    wire:click="{{ $chip['remove'] === 'all' ? 'removeTableFilter' : 'removeTableViewQuickFilter' }}({{ Js::from($chip['name']) }})"
                                ></x-slot>
                            @endif
                        </x-filament::badge>
                    </x-slot>

                    <div class="grid gap-y-3 p-4">
                        <div class="flex items-center justify-between gap-x-2">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $chip['filter']->getLabel() }}</span>

                            <x-filament::icon-button
                                size="sm"
                                color="warning"
                                :icon="FilamentIcon::resolve($chip['favorite'] ? 'table-views::quick-filters.favorite' : 'table-views::quick-filters.unfavorite')"
                                :label="__($chip['favorite'] ? 'table-views::quick-filters.actions.unfavorite' : 'table-views::quick-filters.actions.favorite')"
                                :tooltip="__($chip['favorite'] ? 'table-views::quick-filters.actions.unfavorite' : 'table-views::quick-filters.actions.favorite')"
                                wire:click="togglePinnedTableViewFilter({{ Js::from($chip['name']) }})"
                            />
                        </div>

                        @if ($form = $this->getTableViewQuickFilterForm($chip['name']))
                            <div wire:key="table-views-quick-filter-form-{{ $this->getId() }}-{{ $chip['name'] }}">
                                {{ $form }}
                            </div>
                        @else
                            <div
                                class="flex justify-center py-2"
                                wire:key="table-views-quick-filter-loading-{{ $this->getId() }}-{{ $chip['name'] }}"
                            >
                                <x-filament::loading-indicator class="h-5 w-5 text-gray-400" />
                            </div>
                        @endif

                        @if ($deferred)
                            <div>
                                <x-filament::button
                                    size="sm"
                                    wire:click="applyTableFilters"
                                    x-on:click="close(); $wire.closeTableViewQuickFilter({{ Js::from($chip['name']) }})"
                                >
                                    {{ __('table-views::quick-filters.apply') }}
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </x-filament::dropdown>
            @endforeach
        </div>

        @if ($canClear)
            <div class="mt-0.5">
                <x-filament::icon-button
                    color="gray"
                    size="sm"
                    :icon="FilamentIcon::resolve('table-views::quick-filters.clear')"
                    :label="__('table-views::quick-filters.clear_all')"
                    :tooltip="__('table-views::quick-filters.clear_all')"
                    wire:click="removeTableFilters"
                />
            </div>
        @endif
    </div>
@endif
