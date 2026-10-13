# Upgrading

Notes for Filament 3.

## Pick the right branch

TableKit has one branch per Filament version. See the README for the table. Do not mix them: each branch needs its own Filament and Livewire version.

- This branch needs Filament `^3.3` and Laravel 11.
- The Filament 4 version lives on `filament-v4`. The Filament 5 version lives on `main`.

## Moving to Filament 4 or 5

Saved views carry over. The stored format is the same, and newer branches convert old values when a view is applied. See [Database](database.md#stored-state).

The column list of this branch is replaced on Filament 4 and 5 by Filament's own column manager. Turn on `reorderableColumns()` on those tables.

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
  The Sort button appears and the column toggle dropdown shows the column list.
  Turn them off globally or per table if you do not want them (see [Configuration](configuration.md)).
- Quick filters stay off until you enable them.
