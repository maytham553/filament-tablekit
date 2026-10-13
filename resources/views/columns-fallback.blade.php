@php
    use Filament\Support\Facades\FilamentIcon;

    $enabled = $this->isTableViewColumnOrderEnabled() && ! $this->getTable()->hasToggleableColumns();
@endphp

@if ($enabled)
    <x-filament::dropdown
        placement="bottom-end"
        shift
        teleport
        width="xs"
        class="fi-tablekit-columns-fallback"
        wire:key="tablekit-columns-dropdown-{{ $this->getId() }}"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                color="gray"
                :icon="FilamentIcon::resolve('tablekit::columns.open')"
                :label="__('tablekit::columns.label')"
                :tooltip="__('tablekit::columns.label')"
            />
        </x-slot>

        <div class="grid gap-y-4 p-6">
            <h4 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                {{ __('tablekit::columns.heading') }}
            </h4>

            @include('tablekit::columns-list')
        </div>
    </x-filament::dropdown>
@endif
