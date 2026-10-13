@php
    use TableKit\Support\TableKitOptions;
    use Filament\Support\Enums\IconSize;
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
    class="fi-tablekit-manager-row"
    style="list-style: none"
>
    {{-- Alpine's x-show drops an inline display value from its own element, so the flex layout lives one level down. --}}
    <div style="display: flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; border-radius: 0.5rem">
    @if ($sortable)
        <x-tablekit::sortable-handle :name="$item->label" />
    @endif

    <button
        type="button"
        style="display: flex; flex: 1 1 0%; min-width: 0; align-items: center; gap: 0.5rem; padding: 0.25rem 0; border: 0; background: none; color: inherit; font-size: 0.875rem; text-align: start; cursor: pointer"
        data-active="{{ $isActive ? 'true' : 'false' }}"
        wire:click="{{ $applyCall }}"
        @if ($inDropdown) x-on:click="close($event)" @endif
    >
        <span
            style="width: 0.625rem; height: 0.625rem; flex-shrink: 0; border-radius: 9999px; background-color: var(--{{ $item->color }}-500)"
            aria-hidden="true"
        ></span>

        @if ($item->icon && ($icon = FilamentIcon::resolve('tablekit::icons.' . $item->icon)))
            <x-filament::icon :icon="$icon" :size="IconSize::Small" style="flex-shrink: 0; color: var(--gray-400)" />
        @endif

        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $item->label }}</span>

        @if ($isActive)
            <x-filament::icon
                :icon="FilamentIcon::resolve('tablekit::manager.active')"
                :size="IconSize::Small"
                style="flex-shrink: 0; color: var(--primary-600)"
            />
            <span class="fi-sr-only">{{ __('tablekit::manager.active') }}</span>
        @endif
    </button>

    @if ($isPreset)
        @if ($options->presetViewLockIcon())
            <x-filament::icon
                :icon="FilamentIcon::resolve('tablekit::manager.preset')"
                :size="IconSize::Small"
                style="flex-shrink: 0; color: var(--gray-400)"
            />
            <span class="fi-sr-only">{{ __('tablekit::manager.type.preset') }}</span>
        @endif
    @else
        <x-filament::icon
            :icon="FilamentIcon::resolve('tablekit::manager.user-view')"
            :size="IconSize::Small"
            style="flex-shrink: 0; color: var(--gray-400)"
        />
        <span class="fi-sr-only">{{ __('tablekit::manager.type.user') }}</span>
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
</div>
</li>
