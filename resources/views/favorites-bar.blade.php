@php
    use Filament\Support\Facades\FilamentIcon;

    $activeId = $this->activeTableViewId;
    $modified = $this->isActiveTableViewModified();
    $views = $this->getTableViewsForBar();
    $activeRing = 'box-shadow: 0 0 0 2px var(--primary-500)';
@endphp

<div
    wire:key="table-views-bar-{{ $this->getId() }}"
    class="fi-table-views-bar"
    style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem"
>
    <x-filament::button
        size="sm"
        :color="$activeId === null ? 'primary' : 'gray'"
        :outlined="$activeId !== null"
        :icon="FilamentIcon::resolve('table-views::favorites-bar.default')"
        :style="$activeId === null ? $activeRing : null"
        :aria-pressed="$activeId === null ? 'true' : 'false'"
        data-default="true"
        wire:click="applyDefaultTableView"
    >
        {{ __('table-views::favorites-bar.default') }}
    </x-filament::button>

    @foreach ($views as $view)
        @php
            $isActive = $activeId !== null && (string) $activeId === (string) $view->getKey();
        @endphp

        <x-filament::button
            size="sm"
            :color="$view->resolvedColor()"
            :outlined="! $isActive"
            :icon="$view->icon ? FilamentIcon::resolve('table-views::icons.' . $view->icon) : null"
            :title="$isActive && $modified ? __('table-views::favorites-bar.modified') : null"
            :style="$isActive ? $activeRing : null"
            :aria-pressed="$isActive ? 'true' : 'false'"
            :data-active="$isActive ? 'true' : 'false'"
            wire:key="table-view-{{ $view->getKey() }}"
            wire:click="applyTableView({{ $view->getKey() }})"
        >
            {{ $view->name }}@if ($isActive && $modified)<span aria-hidden="true"> *</span><span class="sr-only">{{ __('table-views::favorites-bar.modified') }}</span>@endif
        </x-filament::button>
    @endforeach

    {{ $this->quickSaveTableViewAction }}
</div>
