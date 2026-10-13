<?php

return [
    // The migration creates this table, so it is config only.
    'table' => 'tablekit_views',

    // Per user preferences (favorites, order, managed default). Also config only, the migration reads it.
    'preferences_table' => 'tablekit_preferences',

    // null = the app's default connection at the time of use. In an app that switches the default connection
    // per panel or module, pin one connection here or per panel with TableKitPlugin::connection().
    // The table must exist on that connection (php artisan migrate --database=<connection>).
    'connection' => null,

    // Set to false when the host app owns the migration (publish tag: tablekit-migrations).
    'load_migrations' => true,

    // user_id is stored as a string, so integer, ULID and UUID keys all work, and user_type (the user's morph class) keeps
    // two user models with the same ids apart. `model` is used only by the user() relation.
    'user' => [
        'model' => null,
    ],

    'favorites_bar' => [
        'enabled' => true,
        // A divider between preset views and the user's own views.
        'divider' => true,
    ],

    'view_manager' => [
        'enabled' => true,
        'slide_over' => false,
        // 'start' = before the Default button, 'end' = after Save view.
        'position' => 'end',
        // null = show the search box when there are more than 8 views.
        'search' => null,
    ],

    'preset_views' => [
        // false = users cannot reorder or unfavorite presets.
        'manageable' => true,
        // Where presets the user has not ranked yet go: 'before' or 'after' the ranked ones.
        'new_sort_position' => 'after',
        // Lock icon on preset rows in the manager.
        'lock_icon' => true,
        // Allow Quick Save while a preset with a modifyQueryUsing is active. The hidden query is not saved.
        'create_from_preset_with_query' => false,
    ],

    // Let each user mark one view per table as their default.
    'managed_default_views' => false,

    // Sort by several columns. Per table: `multiSortTableViews()` can only turn it off (return false); it cannot turn it on.
    'multi_sort' => [
        'enabled' => true,
        'max' => 5,
    ],

    // Clickable filter chips (replaces Filament's indicator bar). Per table: `quickFiltersTableViews()` can only turn it off (return false).
    'quick_filters' => [
        'enabled' => false,
        // A multi-select chip shows this many values, then "& N more". 0 = show all.
        'limit_labels' => 2,
    ],

    // Save and apply the column order (made in Filament's column manager, `reorderableColumns()`) in views and presets. Per table: `columnOrderTableViews()` can only turn it off (return false).
    'column_reordering' => [
        'enabled' => true,
    ],

    'quick_save' => [
        'slide_over' => false,
        'max_name_length' => 100,
    ],

    // Most views one user can save per table (and tenant). Quick Save is refused at the limit; editing is not.
    'max_views_per_table' => 100,

    // Swatches offered in the color picker, in this order. Filament's own names plus Tailwind palettes the plugin registers
    // (unless the app already registered the same name). A color Filament does not know is not offered.
    // The stored value is the name, so views saved with any of these keep working.
    'colors' => [
        'primary', 'gray', 'success', 'warning', 'danger', 'info',
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo',
        'violet', 'purple', 'fuchsia', 'pink', 'rose', 'slate',
    ],

    // Icon keys offered in the icon picker, in this order. Each key is the Filament icon alias `tablekit::icons.<key>`
    // (heroicon default, remappable); a key without an alias is not offered. Add your own with
    // FilamentIcon::register(['tablekit::icons.rocket' => '...']) plus the label in tablekit::icons.rocket.
    'icons' => [
        // status and highlights
        'star', 'heart', 'flag', 'bookmark', 'fire', 'bolt', 'sparkles', 'trophy', 'rocket', 'light-bulb', 'thumb-up',
        'thumb-down', 'smile', 'check-circle', 'x-circle', 'warning', 'exclamation-circle', 'info', 'question',
        'no-symbol', 'shield-check', 'shield-exclamation', 'lock', 'unlock',
        // people
        'user', 'users', 'user-circle', 'user-group', 'user-plus', 'identification', 'academic-cap',
        // commerce and finance
        'currency', 'currency-dollar', 'credit-card', 'wallet', 'receipt', 'shopping-cart', 'shopping-bag', 'store',
        'gift', 'ticket', 'scale', 'calculator', 'briefcase', 'bank', 'cube', 'truck',
        // time
        'clock', 'calendar', 'calendar-days', 'bell', 'bell-alert', 'refresh', 'play',
        // documents
        'document', 'document-duplicate', 'document-check', 'document-search', 'clipboard', 'clipboard-list',
        'clipboard-check', 'newspaper', 'book', 'paperclip', 'pencil', 'edit', 'folder', 'archive', 'inbox',
        'printer',
        // communication
        'envelope', 'chat', 'chat-bubbles', 'phone', 'paper-airplane', 'megaphone', 'share', 'link',
        // places
        'map-pin', 'map', 'globe', 'globe-region', 'building-office', 'building-office-2', 'home',
        // charts and data
        'chart-bar', 'chart-pie', 'chart-line', 'trending-up', 'trending-down', 'table',
        // organize
        'tag', 'funnel', 'search', 'eye', 'eye-slash', 'list', 'queue', 'squares', 'stack', 'adjustments',
        // arrows
        'arrow-up', 'arrow-down', 'arrow-left', 'arrow-right', 'swap', 'undo', 'download', 'upload',
        // tools and misc
        'cog', 'wrench', 'key', 'cloud', 'code', 'sun', 'moon', 'camera', 'photo', 'language', 'fingerprint',
        'plus-circle', 'minus-circle', 'trash',
    ],

    'persist_active_view_in_session' => false,

    'state' => [
        // Also save/apply the ListRecords `activeTab`.
        'tab' => false,
        'max_bytes' => 65536,
    ],
];
