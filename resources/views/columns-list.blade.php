@php
    use Filament\Support\Facades\FilamentIcon;

    $columns = collect($this->getTable()->getColumns())->reject(fn ($column): bool => $column->isHidden());
    $grid = $this->getTable()->getColumnToggleFormColumns();
    $grid = is_array($grid) ? $grid : ['lg' => $grid];
@endphp

<div wire:key="tablekit-columns-{{ $this->getId() }}">
    <x-tablekit::sortable tag="div" method="reorderTableViewColumns" scope-only>
        <x-filament::grid
            :default="$grid['default'] ?? null"
            :sm="$grid['sm'] ?? null"
            :md="$grid['md'] ?? null"
            :lg="$grid['lg'] ?? null"
            :xl="$grid['xl'] ?? null"
            :two-xl="$grid['2xl'] ?? null"
            class="fi-tablekit-columns gap-x-6 gap-y-1"
            x-sortable
            x-on:end.stop="$event.oldIndex !== $event.newIndex && saveOrder($el.sortable.toArray())"
        >
            @foreach ($columns as $name => $column)
                @php
                    $name = (string) $name;
                @endphp

                <div
                    class="flex items-center gap-x-1"
                    wire:key="tablekit-column-{{ $name }}"
                    data-column-row="{{ $name }}"
                    x-sortable-item="{{ $name }}"
                >
                    <x-tablekit::sortable-handle :name="$column->getLabel()" />

                    @if ($column->isToggleable())
                        <label class="flex min-w-0 flex-1 items-center gap-x-3 text-sm text-gray-950 dark:text-white">
                            <x-filament::input.checkbox wire:model.live="toggledTableColumns.{{ $name }}" />

                            <span class="truncate">{{ $column->getLabel() }}</span>
                        </label>
                    @else
                        <span class="min-w-0 flex-1 truncate ps-7 text-sm text-gray-500 dark:text-gray-400" title="{{ __('tablekit::columns.always_shown') }}">{{ $column->getLabel() }}</span>
                    @endif
                </div>
            @endforeach
        </x-filament::grid>
    </x-tablekit::sortable>

    @if ($this->tableViewColumnOrder !== [])
        <div class="pt-2">
            <x-filament::link tag="button" color="gray" size="sm" :icon="FilamentIcon::resolve('tablekit::columns.reset')" wire:click="resetTableViewColumnOrder">
                {{ __('tablekit::columns.reset') }}
            </x-filament::link>
        </div>
    @endif
</div>
