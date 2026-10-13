@php
    use Elite\TableViews\TableViewsServiceProvider;
    use Filament\Support\Enums\IconSize;
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
        class="fi-ta-filter-indicators fi-table-views-quick-filters"
        wire:key="table-views-quick-filters-{{ $this->getId() }}"
    >
        <div>
            <span class="fi-ta-filter-indicators-label">
                {{ __('filament-tables::table.filters.indicator') }}
            </span>

            <div class="fi-ta-filter-indicators-badges-ctn" role="list">
                @foreach ($searchIndicators as $index => $indicator)
                    <x-filament::badge :color="$indicator->getColor()" role="listitem" wire:key="table-views-search-chip-{{ $index }}">
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
                    @php
                        $nameJs = Js::from($chip['name']);
                        $open = $this->tableViewOpenQuickFilter === $chip['name'];
                        $filterLabel = strip_tags((string) $chip['filter']->getLabel());
                        $valueLabel = strip_tags((string) $chip['label']);
                        $ariaLabel = __('table-views::quick-filters.edit', ['filter' => $filterLabel]).($chip['active'] && $valueLabel !== $filterLabel ? ': '.$valueLabel : '');
                    @endphp

                    <x-filament::dropdown
                        placement="bottom-start"
                        shift
                        teleport
                        width="sm"
                        :menu="false"
                        role="listitem"
                        class="fi-table-views-quick-filter"
                        :data-quick-filter="$chip['name']"
                        x-on:click.outside="if ($wire.tableViewOpenQuickFilter === {{ $nameJs }}) { $wire.closeTableViewQuickFilter({{ $nameJs }}) }"
                        wire:key="table-views-quick-filter-{{ $this->getId() }}-{{ $chip['name'] }}"
                    >
                        <x-slot name="trigger">
                            <x-filament::badge
                                :color="$chip['color']"
                                :loading-indicator="false"
                                :data-active="$chip['active'] ? 'true' : 'false'"
                                role="button"
                                tabindex="0"
                                :aria-label="$ariaLabel"
                            >
                                {{ $chip['label'] }}

                                <x-filament::icon
                                    :icon="FilamentIcon::resolve('table-views::quick-filters.chevron')"
                                    :size="IconSize::Small"
                                    style="display: inline; opacity: 0.6"
                                />

                                @if ($chip['remove'] !== null)
                                    <x-slot
                                        name="deleteButton"
                                        :label="__('table-views::quick-filters.actions.remove')"
                                        x-on:mousedown.stop=""
                                        x-on:keydown.stop=""
                                        wire:click="{{ $chip['remove'] === 'all' ? 'removeTableFilter' : 'removeTableViewQuickFilter' }}({{ $nameJs }})"
                                    ></x-slot>
                                @endif
                            </x-filament::badge>
                        </x-slot>

                        <div style="display: grid; gap: 0.75rem; padding: 1rem">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem">
                                <span style="font-size: 0.875rem; font-weight: 600">{{ $chip['filter']->getLabel() }}</span>

                                <x-filament::icon-button
                                    size="sm"
                                    color="warning"
                                    :icon="FilamentIcon::resolve($chip['favorite'] ? 'table-views::quick-filters.favorite' : 'table-views::quick-filters.unfavorite')"
                                    :label="__($chip['favorite'] ? 'table-views::quick-filters.actions.unfavorite' : 'table-views::quick-filters.actions.favorite')"
                                    :tooltip="__($chip['favorite'] ? 'table-views::quick-filters.actions.unfavorite' : 'table-views::quick-filters.actions.favorite')"
                                    wire:click="togglePinnedTableViewFilter({{ $nameJs }})"
                                />
                            </div>

                            @if ($form = $this->getTableViewQuickFilterForm($chip['name']))
                                <div wire:key="table-views-quick-filter-form-{{ $this->getId() }}-{{ $chip['name'] }}">
                                    {{ $form }}
                                </div>
                            @elseif (! $open)
                                <div
                                    wire:key="table-views-quick-filter-loading-{{ $this->getId() }}-{{ $chip['name'] }}"
                                    x-intersect.once="$wire.tableViewOpenQuickFilter === {{ $nameJs }} || $wire.openTableViewQuickFilter({{ $nameJs }})"
                                    style="display: flex; justify-content: center; padding: 0.5rem 0"
                                >
                                    <x-filament::loading-indicator style="width: 1.25rem; height: 1.25rem; color: var(--gray-400)" />
                                </div>
                            @endif

                            @if ($deferred)
                                <div>
                                    <x-filament::button
                                        size="sm"
                                        wire:click="applyTableFilters"
                                        x-on:click="close(); $wire.closeTableViewQuickFilter({{ $nameJs }})"
                                    >
                                        {{ __('table-views::quick-filters.apply') }}
                                    </x-filament::button>
                                </div>
                            @endif
                        </div>
                    </x-filament::dropdown>
                @endforeach
            </div>
        </div>

        @if ($canClear)
            {{ $table->getFiltersRemoveAllAction() }}
        @endif
    </div>
@endif
