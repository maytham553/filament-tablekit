# Database

TableKit keeps its data in two tables. This page covers the tables, migrations, the connection, tenancy, user keys, privacy and the stored view format.

## Tables

| Table | Config key | What it holds |
| --- | --- | --- |
| `tablekit_views` | `table` | The views users save. |
| `tablekit_preferences` | `preferences_table` | Per-user choices: favorite, order, hidden and default. For presets and for user views (`is_default`). |

The table names are config only, because the migrations read them.

**`tablekit_views`** columns: `id`, `user_type`, `user_id`, `name`, `icon`, `color`, `resource`, `state` (jsonb), `is_favorite`, `sort`, `tenant_type`, `tenant_id`, timestamps. One index covers the lookup by user, resource, tenant, favorite and sort.

**`tablekit_preferences`** columns: `id`, `user_type`, `user_id`, `tenant_type`, `tenant_id`, `resource`, `view_type`, `view_key`, `is_favorite` (null means the preset's own default), `is_hidden`, `sort`, `is_default`, timestamps. It has a lookup index. On Postgres and SQLite it also gets a unique index, created with raw SQL (`coalesce` makes rows without a tenant collide too, because NULLs are distinct in a plain unique index).

The Eloquent models are `TableKit\Models\TableView` and `TableKit\Models\TablePreference`.

## Run the migrations

The migrations load by themselves:

```bash
php artisan migrate
```

Run the `tablekit_preferences` migration before you deploy code that defines presets or turns on managed defaults. Components without presets run no preferences query.

### Publish the migrations

If your app owns its migrations, do these three steps **in this order**:

1. Turn off the automatic loading. Publish the config first if you have not (`php artisan vendor:publish --tag=tablekit-config`), then set it in `config/tablekit.php`:

   ```php
   'load_migrations' => false,
   ```

2. Publish the migrations:

   ```bash
   php artisan vendor:publish --tag=tablekit-migrations   # both migration files
   ```

3. Run them:

   ```bash
   php artisan migrate
   ```

If you publish while loading is still on, `php artisan migrate` sees two copies of each migration (the package's and yours) and fails with "table already exists" on the second one. The same happens if you already ran `php artisan migrate` and publish afterwards. In both cases, set `load_migrations` to `false` and either delete the published copies, or rename the rows in your `migrations` table to the published file names. Do not run the copies on top of existing tables.

## Database connection

`connection` is `null` by default. That means the app's default connection **at the moment of use**. If your app changes `database.default` per panel or module, views would be read from a different database than the one you migrated. In that case, pin the connection. The plugin value wins over the config value.

```php
// config/tablekit.php
'connection' => 'reporting',

// or per panel
TableKitPlugin::make()->connection('reporting')
```

The migration uses `config('tablekit.connection')`, or the default connection when it is `null`. For a per-panel connection, run only the TableKit migrations against that connection. `--database` alone would run **all** of your app's migrations there, so add `--path`:

```bash
php artisan migrate --database=reporting --path=vendor/tablekit/filament-tablekit/database/migrations
```

Each connection that serves a panel with the plugin needs its own TableKit tables.

`TableView::user()` reads the user from the pinned connection too. If your users live on another connection, set `$connection` on the user model.

## Tenancy

- Views are stored per user, per tenant (`Filament::getTenant()`) and per component class.
- `tenant_type` is the tenant's morph class and `tenant_id` is its key as a string.
- Without a tenant, both columns are `null`.
- The unique name check (per user, table and tenant) and all reads and writes use the same scope. Views never leak across users, tenants or tables.
- To change the table key, override the protected `getTableViewsResourceKey()` on the component. The default is the component class. For example, return the same string from two classes to share views.

## User keys

`user_id` is a string column (64 characters). Integer, ULID and UUID user keys all work with no setting.

- `user_type` is the auth user's morph class (`getMorphClass()`). A morph map alias works.
- `user_id` is the auth identifier (`Filament::auth()->id()`), compared as a string.
- Views and preferences belong to a user **type and id**. Two panels or guards with their own user models that share the same ids never see each other's views.
- There is no foreign key to the users table, so any key type works.
- `user.model` (or `->userModel()`) is only used by the `TableView::user()` relation.

## Delete a user's views

Views are **not** deleted when a user is deleted. A view stores the table's search text and filter values, which can hold personal data the user typed. Delete the user's rows when you delete the account, for example in the user's `deleted` event:

```php
use TableKit\Models\TablePreference;
use TableKit\Models\TableView;

TableView::query()
    ->where('user_type', $user->getMorphClass())
    ->where('user_id', (string) $user->getKey())
    ->delete();

TablePreference::query()
    ->where('user_type', $user->getMorphClass())
    ->where('user_id', (string) $user->getKey())
    ->delete();
```

Deleting a user view also deletes its preference rows.

## Stored state

The `state` column holds JSON like this:

```json
{"v":1,"filters":{"status":{"value":null}},"search":"","columnSearches":{},"sort":{"column":null,"direction":null},"toggledColumns":{},"grouping":{"column":null,"direction":null}}
```

- `filters` holds each filter's own state, under the filter's name. Its shape depends on the filter, for example `{"status":{"value":"shipped"}}` or `{"ordered":{"from":null,"until":null}}`.
- Empty maps are stored as `{}`.
- Readers ignore unknown keys and treat missing keys as empty.
- `v` is the format version. It stays at 1.
- With `state.tab` on, a `tab` key is added.

Three more keys are optional (`sorts`, `columnOrder` and `favoriteFilters`). They are written only when they differ from the default, so a view without them looks exactly like an older view:

```json
{"sort":{"column":"amount","direction":"desc"},
 "sorts":[{"column":"amount","direction":"desc"},{"column":"ordered_at","direction":"asc"}],
 "columnOrder":["reference","status","amount"],
 "favoriteFilters":["status","is_paid"]}
```

| Key | Meaning |
| --- | --- |
| `sort` | Always the first entry of `sorts`. A reader without multi-sort still sees a valid single sort. |
| `sorts` | The whole multi-sort. Present only with two or more entries. |
| `columnOrder` | The full column order. Absent means the code order, or no opinion. |
| `favoriteFilters` | Pinned quick filters. Absent means the developer's `favorite()` filters. `[]` means none. |

Invalid entries are dropped when a view is applied.

The shape is the same as the Filament 3 version's, so views saved there load here. A nested `toggledColumns` map (Filament 3 stored dotted column names as nested arrays) is flattened, and sort and grouping are converted to Filament 4's `column:direction` properties when the view is applied.
