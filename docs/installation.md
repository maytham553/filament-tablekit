# Installation

How to install TableKit for Filament 5, and what the publish tags do.

## Requirements

- An existing Filament 5 panel.
- PHP 8.2 or newer.
- Laravel 11.28+, 12 or 13 (Filament 5.8.4+ and Livewire 4).

## Install with Composer

```bash
composer require tablekit/filament-tablekit
composer require tablekit/filament-tablekit:^5.0   # or pin the version for Filament 5
```

TableKit has one version per Filament version: `^5.0` for Filament 5, `^4.0` for Filament 4 and `^3.0` for Filament 3. Composer picks the right one from the Filament you have installed, so the plain `composer require` is enough.

## Install from GitHub

Use this before a release is on Packagist, or to follow a branch.

```bash
composer config repositories.tablekit vcs https://github.com/maytham553/filament-tablekit
composer require "tablekit/filament-tablekit:5.x-dev"
```

`5.x-dev` is the branch `main`. The other branches are `5.x-dev` (`main`), `4.x-dev` (`filament-v4`) and `3.x-dev` (`filament-v3`). Pick the one for your Filament version.

## After installing

1. Run `php artisan migrate`. The migrations load by themselves. If your app owns its migrations, see [Database](database.md#publish-the-migrations).
2. Add the `HasTableKit` trait to a list page, relation manager or table widget (see the README).
3. Optional: register `TableKitPlugin::make()` on your panel to change options (see [Configuration](configuration.md)). Everything works without it.

No theme or CSS step is needed.

## Publish tags

Nothing needs publishing. Publish only what you want to change:

| Tag | What it copies | When to use it |
| --- | --- | --- |
| `tablekit-config` | `config/tablekit.php` | To change the config file. See [Configuration](configuration.md). |
| `tablekit-migrations` | Both migration files into `database/migrations` | Only if your app owns its migrations. Read [Database](database.md#publish-the-migrations) first. |
| `tablekit-translations` | `lang/vendor/tablekit` | To change a text or add a language. See [Colors and icons](colors-and-icons.md#translations). |
| `tablekit-views` | `resources/views/vendor/tablekit` | To change the Blade markup. |

```bash
php artisan vendor:publish --tag=tablekit-views
```

The views are not meant to be published. A published copy no longer gets fixes from the package when you upgrade, so you must compare it with the new version yourself. Prefer the options, the icon aliases and the translations.
