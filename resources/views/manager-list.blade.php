@php
    use Elite\TableViews\Support\TableViewsOptions;

    $options = TableViewsOptions::resolve();
    $catalog = $this->getTableViewsCatalog();
    $inDropdown ??= false;
    $total = $catalog->count();
    $showSearch = $options->viewManagerSearch() ?? ($total > 8);

    $manageablePresets = $options->presetViewsManageable();

    $sections = [
        'favorites' => [
            ['type' => 'preset', 'favorite' => true, 'items' => $catalog->favoritePresets()],
            ['type' => 'user', 'favorite' => true, 'items' => $catalog->favoriteUserViews()],
        ],
        'my_views' => [['type' => 'user', 'favorite' => false, 'items' => $catalog->myViews()]],
        'presets' => [['type' => 'preset', 'favorite' => false, 'items' => $catalog->presetViews()]],
    ];
@endphp

<div
    x-data="{ search: '' }"
    wire:key="table-views-manager-list-{{ $this->getId() }}"
    class="fi-table-views-manager-list divide-y divide-gray-100 dark:divide-white/5"
>
    @if ($showSearch)
        <div class="p-3">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    x-model="search"
                    :placeholder="__('table-views::manager.search')"
                    :aria-label="__('table-views::manager.search')"
                />
            </x-filament::input.wrapper>
        </div>
    @endif

    @if ($total === 0)
        <p class="p-4 text-sm text-gray-500 dark:text-gray-400">
            {{ __('table-views::manager.empty') }}
        </p>
    @endif

    @foreach ($sections as $section => $groups)
        @if (collect($groups)->contains(fn (array $group): bool => $group['items']->isNotEmpty()))
            <div class="p-2" wire:key="table-views-manager-section-{{ $section }}">
                <h4 class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('table-views::manager.sections.' . $section) }}
                </h4>

                @foreach ($groups as $group)
                    @continue($group['items']->isEmpty())

                    @php
                        $sortable = $group['items']->count() >= 2 && ($group['type'] === 'user' || $manageablePresets);
                    @endphp

                    <x-table-views::sortable
                        method="reorderTableViews"
                        :arguments="[$group['type'], $group['favorite']]"
                        :enabled="$sortable"
                        wire:key="table-views-manager-group-{{ $section }}-{{ $group['type'] }}"
                    >
                        @foreach ($group['items'] as $item)
                            @include('table-views::manager-row', ['item' => $item, 'sortable' => $sortable, 'inDropdown' => $inDropdown])
                        @endforeach
                    </x-table-views::sortable>
                @endforeach
            </div>
        @endif
    @endforeach
</div>
