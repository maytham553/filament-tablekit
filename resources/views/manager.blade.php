@php
    use Elite\TableViews\Support\TableViewsOptions;
    use Filament\Support\Facades\FilamentIcon;

    $slideOver = TableViewsOptions::resolve()->isViewManagerSlideOver();
@endphp

@if ($slideOver)
    {{ $this->manageTableViewsAction }}
@else
    <x-filament::dropdown
        placement="bottom-end"
        shift
        width="md"
        max-height="24rem"
        teleport
        class="fi-table-views-manager"
        wire:key="table-views-manager-{{ $this->getId() }}"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                size="sm"
                color="gray"
                :icon="FilamentIcon::resolve('table-views::manager.open')"
                :label="__('table-views::manager.label')"
                :tooltip="__('table-views::manager.label')"
                x-on:click="$wire.tableViewsManagerLoaded || $wire.openTableViewsManager()"
            />
        </x-slot>

        @if ($this->tableViewsManagerLoaded)
            @include('table-views::manager-list', ['inDropdown' => true])
        @else
            <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
                {{ __('table-views::manager.loading') }}
            </div>
        @endif
    </x-filament::dropdown>
@endif
