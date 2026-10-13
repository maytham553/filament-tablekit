@php
    use TableKit\Support\TableKitOptions;
    use Filament\Support\Facades\FilamentIcon;
    use Illuminate\Support\Js;

    $options = TableKitOptions::resolve();
    $isPreset = $item->isPreset();
    $canManage = ! $isPreset || $options->presetViewsManageable();
    $isActive = $isPreset
        ? $this->activeTablePresetKey === $item->key
        : ($this->activeTableViewId !== null && (string) $this->activeTableViewId === $item->key);
    $applyCall = $isPreset
        ? 'applyTablePresetView(' . Js::from($item->key) . ')'
        : 'applyTableView(' . (int) $item->key . ')';
    $closeDropdown = $inDropdown ? 'close($event)' : null;
    $targetArguments = Js::from($item->type) . ', ' . Js::from($item->key);
@endphp

<li
    wire:key="tablekit-manager-row-{{ $item->type }}-{{ $item->key }}"
    data-name="{{ mb_strtolower($item->label) }}"
    data-type="{{ $item->type }}"
    @if ($sortable) x-sortable-item="{{ $item->key }}" @endif
    x-show="! search || $el.dataset.name.includes(search.toLowerCase())"
    class="flex items-center gap-x-1 rounded-lg px-2 py-1 hover:bg-gray-50 dark:hover:bg-white/5"
>
    @if ($sortable)
        <x-tablekit::sortable-handle :name="$item->label" />
    @endif

    <button
        type="button"
        class="flex min-w-0 flex-1 items-center gap-x-2 py-1 text-start text-sm text-gray-950 dark:text-white"
        data-active="{{ $isActive ? 'true' : 'false' }}"
        wire:click="{{ $applyCall }}"
        @if ($inDropdown) x-on:click="close($event)" @endif
    >
        <span
            class="fi-color-custom h-2.5 w-2.5 shrink-0 rounded-full bg-custom-500"
            style="{{ \Filament\Support\get_color_css_variables($item->color, shades: [500]) }}"
            aria-hidden="true"
        ></span>

        @if ($item->icon && ($icon = FilamentIcon::resolve('tablekit::icons.' . $item->icon)))
            <x-filament::icon :icon="$icon" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
        @endif

        <span class="truncate">{{ $item->label }}</span>

        @if ($isActive)
            <x-filament::icon
                :icon="FilamentIcon::resolve('tablekit::manager.active')"
                class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400"
            />
            <span class="sr-only">{{ __('tablekit::manager.active') }}</span>
        @endif
    </button>

    @if ($isPreset)
        @if ($options->presetViewLockIcon())
            <x-filament::icon
                :icon="FilamentIcon::resolve('tablekit::manager.preset')"
                class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500"
            />
            <span class="sr-only">{{ __('tablekit::manager.type.preset') }}</span>
        @endif
    @else
        <x-filament::icon
            :icon="FilamentIcon::resolve('tablekit::manager.user-view')"
            class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500"
        />
        <span class="sr-only">{{ __('tablekit::manager.type.user') }}</span>
    @endif

    @if ($canManage)
        <x-filament::icon-button
            size="sm"
            color="gray"
            :icon="FilamentIcon::resolve($item->isFavorite ? 'tablekit::manager.favorite' : 'tablekit::manager.unfavorite')"
            :label="$item->isFavorite ? __('tablekit::manager.actions.unfavorite') : __('tablekit::manager.actions.favorite')"
            :tooltip="$item->isFavorite ? __('tablekit::manager.actions.unfavorite') : __('tablekit::manager.actions.favorite')"
            wire:click="toggleTableViewFavorite({{ $targetArguments }})"
        />
    @endif

    @if ($options->managedDefaultViews())
        <x-filament::icon-button
            size="sm"
            :color="$item->isDefault ? 'warning' : 'gray'"
            :icon="FilamentIcon::resolve($item->isDefault ? 'tablekit::manager.remove-default' : 'tablekit::manager.default')"
            :label="$item->isDefault ? __('tablekit::manager.actions.remove_default') : __('tablekit::manager.actions.default')"
            :tooltip="$item->isDefault ? __('tablekit::manager.actions.remove_default') : __('tablekit::manager.actions.default')"
            wire:click="toggleTableViewDefault({{ $targetArguments }})"
        />
    @endif

    @if (! $isPreset)
        <x-filament::icon-button
            size="sm"
            color="gray"
            :icon="FilamentIcon::resolve('tablekit::manager.edit')"
            :label="__('tablekit::manager.actions.edit')"
            :tooltip="__('tablekit::manager.actions.edit')"
            wire:click="mountAction('editTableView', { viewId: {{ (int) $item->key }} })"
            :x-on:click="$closeDropdown"
        />

        <x-filament::icon-button
            size="sm"
            color="danger"
            :icon="FilamentIcon::resolve('tablekit::manager.delete')"
            :label="__('tablekit::manager.actions.delete')"
            :tooltip="__('tablekit::manager.actions.delete')"
            wire:click="mountAction('deleteTableView', { viewId: {{ (int) $item->key }} })"
            :x-on:click="$closeDropdown"
        />
    @endif
</li>
