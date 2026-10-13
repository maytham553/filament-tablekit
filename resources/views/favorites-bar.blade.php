@php
    use TableKit\Support\TableKitOptions;
    use Filament\Support\Facades\FilamentIcon;
    use Illuminate\Support\Js;

    $options = TableKitOptions::resolve();
    $catalog = $this->getTableViewsCatalog();
    $activeId = $this->activeTableViewId;
    $activePreset = $this->activeTablePresetKey;
    $modified = $this->isActiveTableViewModified();
    $defaultPreset = $this->resolveTablePresets()['default'] ?? null;
    $managerEnabled = $options->viewManagerEnabled();
    $managerAtStart = $managerEnabled && $options->viewManagerPosition() === 'start';
    $managerAtEnd = $managerEnabled && ! $managerAtStart;
    $favoritePresets = $catalog->favoritePresets();
    $views = $this->getTableViewsForBar();
    $developerDefault = $this->resolveDeveloperDefaultPreset();
    $defaultActive = $activeId === null && ($activePreset === null || $activePreset === $developerDefault?->getKey());
    $activeRing = 'box-shadow: 0 0 0 2px rgb(var(--primary-500))';
    $defaultIcon = FilamentIcon::resolve('tablekit::favorites-bar.default');

    $defaultBadge = $defaultPreset?->getBadge();
    $defaultTooltip = $defaultPreset?->getTooltip();

    if ($defaultPreset?->getIcon()) {
        $defaultIcon = FilamentIcon::resolve('tablekit::icons.' . $defaultPreset->getIcon()) ?? $defaultIcon;
    }
@endphp

<div
    wire:key="tablekit-bar-{{ $this->getId() }}"
    class="fi-tablekit-bar flex flex-wrap items-center gap-2 px-4 py-3 sm:px-6"
>
    @if ($managerAtStart)
        @include('tablekit::manager')
    @endif

    <x-filament::button
        size="sm"
        :color="$defaultActive ? ($defaultPreset?->getColor() ?? 'primary') : 'gray'"
        :outlined="! $defaultActive"
        :icon="$defaultIcon"
        :badge="$defaultBadge"
        :badge-color="$defaultPreset?->getBadgeColor() ?? 'primary'"
        :tooltip="$defaultTooltip"
        :style="$defaultActive ? $activeRing : null"
        :aria-pressed="$defaultActive ? 'true' : 'false'"
        data-default="true"
        wire:click="applyDefaultTableView"
    >
        {{ $defaultPreset ? $defaultPreset->getLabel() : __('tablekit::favorites-bar.default') }}@if ($defaultActive && $modified)<span aria-hidden="true"> *</span><span class="sr-only">{{ __('tablekit::favorites-bar.modified') }}</span>@endif
    </x-filament::button>

    @foreach ($favoritePresets as $item)
        @php
            $isActive = $activePreset === $item->key;
            $badge = $item->preset->getBadge();
            $tooltip = $item->preset->getTooltip();
        @endphp

        <x-filament::button
            size="sm"
            :color="$item->color"
            :outlined="! $isActive"
            :icon="$item->icon ? FilamentIcon::resolve('tablekit::icons.' . $item->icon) : null"
            :badge="$badge"
            :badge-color="$item->preset->getBadgeColor()"
            :tooltip="$isActive && $modified ? __('tablekit::favorites-bar.modified') : $tooltip"
            :style="$isActive ? $activeRing : null"
            :aria-pressed="$isActive ? 'true' : 'false'"
            :data-active="$isActive ? 'true' : 'false'"
            data-preset="true"
            wire:key="table-preset-{{ $item->key }}"
            wire:click="applyTablePresetView({{ Js::from($item->key) }})"
        >
            {{ $item->label }}@if ($isActive && $modified)<span aria-hidden="true"> *</span><span class="sr-only">{{ __('tablekit::favorites-bar.modified') }}</span>@endif
        </x-filament::button>
    @endforeach

    @if ($options->favoritesBarDivider() && $favoritePresets->isNotEmpty() && $views->isNotEmpty())
        <div aria-hidden="true" class="fi-tablekit-divider h-6 w-px bg-gray-200 dark:bg-white/20"></div>
    @endif

    @foreach ($views as $view)
        @php
            $isActive = $activeId !== null && (string) $activeId === (string) $view->getKey();
        @endphp

        <x-filament::button
            size="sm"
            :color="$view->resolvedColor()"
            :outlined="! $isActive"
            :icon="$view->icon ? FilamentIcon::resolve('tablekit::icons.' . $view->icon) : null"
            :title="$isActive && $modified ? __('tablekit::favorites-bar.modified') : null"
            :style="$isActive ? $activeRing : null"
            :aria-pressed="$isActive ? 'true' : 'false'"
            :data-active="$isActive ? 'true' : 'false'"
            wire:key="table-view-{{ $view->getKey() }}"
            wire:click="applyTableView({{ $view->getKey() }})"
        >
            {{ $view->name }}@if ($isActive && $modified)<span aria-hidden="true"> *</span><span class="sr-only">{{ __('tablekit::favorites-bar.modified') }}</span>@endif
        </x-filament::button>
    @endforeach

    {{ $this->quickSaveTableViewAction }}

    @if ($managerAtEnd)
        @include('tablekit::manager')
    @endif
</div>
