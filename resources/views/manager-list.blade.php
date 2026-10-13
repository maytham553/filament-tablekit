@php
    use TableKit\Support\TableKitOptions;

    $options = TableKitOptions::resolve();
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
    wire:key="tablekit-manager-list-{{ $this->getId() }}"
    class="fi-tablekit-manager-list divide-y divide-gray-100 dark:divide-white/5"
>
    @if ($showSearch)
        <div class="p-3">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    x-model="search"
                    :placeholder="__('tablekit::manager.search')"
                    :aria-label="__('tablekit::manager.search')"
                />
            </x-filament::input.wrapper>
        </div>
    @endif

    @if ($total === 0)
        <p class="p-4 text-sm text-gray-500 dark:text-gray-400">
            {{ __('tablekit::manager.empty') }}
        </p>
    @endif

    @foreach ($sections as $section => $groups)
        @if (collect($groups)->contains(fn (array $group): bool => $group['items']->isNotEmpty()))
            <div class="p-2" wire:key="tablekit-manager-section-{{ $section }}">
                <h4 class="fi-tablekit-manager-heading px-2 py-1 text-xs font-semibold text-gray-500 dark:text-gray-400" style="text-transform: uppercase">
                    {{ __('tablekit::manager.sections.' . $section) }}
                </h4>

                @foreach ($groups as $group)
                    @continue($group['items']->isEmpty())

                    @php
                        $sortable = $group['items']->count() >= 2 && ($group['type'] === 'user' || $manageablePresets);
                    @endphp

                    <x-tablekit::sortable
                        method="reorderTableViews"
                        :arguments="[$group['type'], $group['favorite']]"
                        :enabled="$sortable"
                        wire:key="tablekit-manager-group-{{ $section }}-{{ $group['type'] }}"
                    >
                        @foreach ($group['items'] as $item)
                            @include('tablekit::manager-row', ['item' => $item, 'sortable' => $sortable, 'inDropdown' => $inDropdown])
                        @endforeach
                    </x-tablekit::sortable>
                @endforeach
            </div>
        @endif
    @endforeach

    <style>
        .fi-tablekit-manager-heading { letter-spacing: 0.05em; }
        [dir="rtl"] .fi-tablekit-manager-heading { letter-spacing: normal; text-transform: none !important; }
    </style>
</div>
