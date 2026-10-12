<?php

return [
    // The migration creates this table, so it is config only.
    'table' => 'table_views',

    // null = the app's default connection at the time of use. In an app that switches the default connection
    // per panel or module, pin one connection here or per panel with TableViewsPlugin::connection().
    // The table must exist on that connection (php artisan migrate --database=<connection>).
    'connection' => null,

    // Set to false when the host app owns the migration (publish tag: table-views-migrations).
    'load_migrations' => true,

    // user_id is stored as a string, so integer, ULID and UUID keys all work. Used only by the user() relation.
    'user' => [
        'model' => null,
    ],

    'favorites_bar' => [
        'enabled' => true,
    ],

    'quick_save' => [
        'slide_over' => false,
        'max_name_length' => 100,
    ],

    'colors' => ['primary', 'gray', 'success', 'warning', 'danger', 'info'],

    'icons' => [
        'star', 'heart', 'bookmark', 'flag', 'bolt', 'fire', 'funnel', 'clock', 'user', 'users',
        'tag', 'eye', 'chart-bar', 'currency', 'check-circle', 'warning', 'folder', 'inbox',
    ],

    'persist_active_view_in_session' => false,

    'state' => [
        // Also save/apply the ListRecords `activeTab`.
        'tab' => false,
        'max_bytes' => 65536,
    ],
];
