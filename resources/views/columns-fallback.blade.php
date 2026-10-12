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
        class="fi-table-views-columns-fallback"
        wire:key="table-views-columns-dropdown-{{ $this->getId() }}"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                color="gray"
                :icon="FilamentIcon::resolve('table-views::columns.open')"
                :label="__('table-views::columns.label')"
                :tooltip="__('table-views::columns.label')"
            />
        </x-slot>

        <div class="grid gap-y-4 p-6">
            <h4 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                {{ __('table-views::columns.heading') }}
            </h4>

            @include('table-views::columns-list')
        </div>
    </x-filament::dropdown>
@endif
