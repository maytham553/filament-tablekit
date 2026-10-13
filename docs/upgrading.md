# Upgrading

Notes for Filament 4.

## Pick the right branch

TableKit has one branch per Filament version. See the README for the table. Do not mix them: each branch needs its own Filament and Livewire version.

- This branch needs Filament `^4.15` (tested with 4.15 and Livewire 3.8).
- The Filament 5 and Livewire 4 version lives on `main`. The Filament 3 version lives on `filament-v3`.

## Coming from the Filament 3 version

Saved views carry over. The stored format is the same, and newer branches convert old values when a view is applied. See [Database](database.md#stored-state).

- Turn on `->reorderableColumns()` on tables where you want column order saved. The Filament 3 column list is replaced by Filament's own column manager.
- Remap icon aliases that you changed for the Filament 3 version. The alias list changed a little. See [Colors and icons](colors-and-icons.md#icon-aliases-for-the-interface). The `tablekit::quick-filters.clear`, `tablekit::columns.open` and `tablekit::columns.reset` aliases are gone.
- The extra-sorts URL parameter is now `sorts` (it was `tableSorts`).
- Plugin and config options are the same.

## Adding presets and the view manager to an older setup

If you started with only saved views and now add presets, the view manager or managed defaults:

1. Run the new migration, `tablekit_preferences`, before you deploy code that defines presets or turns on managed defaults. It follows the same `tablekit.connection` rule as `tablekit_views` (`php artisan migrate --database=<connection>` for a pinned connection). It adds a unique index through raw SQL on Postgres and SQLite only.
2. If your app owns its migrations, publish with `--tag=tablekit-migrations` and copy the new file.
3. Remap the new icon aliases if you use another icon set. See [Colors and icons](colors-and-icons.md).
4. Components without presets behave as before and run no preferences query.

Both tables have a nullable `user_type` column next to `user_id`. It is part of the lookup and unique indexes.

## Adding multi-sort, quick filters and column order

- No migration is needed. These features extend the stored state JSON, and views saved earlier load unchanged.
- Multi-sort and column order are **on** for every component that uses the trait. 
  The Sort button appears, and column order is saved in views.
  Turn them off globally or per table if you do not want them (see [Configuration](configuration.md)).
- Quick filters stay off until you enable them.
