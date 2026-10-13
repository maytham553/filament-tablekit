@php
    use TableKit\Support\TableKitOptions;
    use Filament\Support\Facades\FilamentIcon;

    $slideOver = TableKitOptions::resolve()->isViewManagerSlideOver();
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
        class="fi-tablekit-manager"
        wire:key="tablekit-manager-{{ $this->getId() }}"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                size="sm"
                color="gray"
                :icon="FilamentIcon::resolve('tablekit::manager.open')"
                :label="__('tablekit::manager.label')"
                :tooltip="__('tablekit::manager.label')"
                x-on:click="$wire.tableViewsManagerLoaded || $wire.openTableViewsManager()"
            />
        </x-slot>

        @if ($this->tableViewsManagerLoaded)
            @include('tablekit::manager-list', ['inDropdown' => true])
        @else
            <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
                {{ __('tablekit::manager.loading') }}
            </div>
        @endif
    </x-filament::dropdown>
@endif
