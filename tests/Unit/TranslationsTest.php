<?php

use Elite\TableViews\Support\IconCatalog;

function flattenKeys(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            array_push($keys, ...flattenKeys($value, $path));
        } else {
            $keys[] = $path;
        }
    }

    sort($keys);

    return $keys;
}

it('ships identical key sets in en and ar', function () {
    $base = __DIR__.'/../../resources/lang';
    $files = array_map('basename', glob("{$base}/en/*.php"));

    expect($files)->not->toBeEmpty()
        ->and(array_map('basename', glob("{$base}/ar/*.php")))->toEqual($files);

    foreach ($files as $file) {
        expect(flattenKeys(require "{$base}/ar/{$file}"))->toBe(flattenKeys(require "{$base}/en/{$file}"), $file);
    }
});

it('has no translation key containing a dot', function () {
    $check = function (array $array) use (&$check) {
        foreach ($array as $key => $value) {
            expect((string) $key)->not->toContain('.');

            if (is_array($value)) {
                $check($value);
            }
        }
    };

    foreach (glob(__DIR__.'/../../resources/lang/en/*.php') as $file) {
        $check(require $file);
    }
});

it('has no empty translations', function () {
    foreach (['en', 'ar'] as $locale) {
        foreach (glob(__DIR__."/../../resources/lang/{$locale}/*.php") as $file) {
            foreach (flattenKeys(require $file) as $key) {
                expect(trim((string) data_get(require $file, $key)))->not->toBe('', "{$locale}/{$key}");
            }
        }
    }
});

it('covers every configured color and icon', function () {
    $colors = require __DIR__.'/../../resources/lang/en/colors.php';
    $icons = require __DIR__.'/../../resources/lang/en/icons.php';
    $config = require __DIR__.'/../../config/table-views.php';

    expect(array_diff($config['colors'], array_keys($colors)))->toBe([])
        ->and(array_diff($config['icons'], array_keys($icons)))->toBe([]);
});

it('translates every catalog icon, its keywords and every color in en and ar', function () {
    foreach (['en', 'ar'] as $locale) {
        $icons = require __DIR__."/../../resources/lang/{$locale}/icons.php";
        $keywords = require __DIR__."/../../resources/lang/{$locale}/icon-keywords.php";
        $colors = require __DIR__."/../../resources/lang/{$locale}/colors.php";

        expect(array_diff(array_keys(IconCatalog::ICONS), array_keys($icons)))->toBe([], "{$locale} icon labels")
            ->and(array_diff(array_keys(IconCatalog::ICONS), array_keys($keywords)))->toBe([], "{$locale} icon keywords")
            ->and(array_diff(array_keys($icons), array_keys(IconCatalog::ICONS)))->toBe([], "{$locale} stray icon labels")
            ->and(array_diff(config('table-views.colors'), array_keys($colors)))->toBe([], "{$locale} colors");
    }
});

it('renders arabic labels when the locale is ar', function () {
    app()->setLocale('ar');

    expect(__('table-views::favorites-bar.default'))->toBe('الافتراضي')
        ->and(__('table-views::quick-save.fields.name'))->toBe('الاسم');
});

it('uses every key of the sort, quick filter and column translations and every alias of those views', function () {
    $root = __DIR__.'/../..';
    $source = '';

    foreach (array_merge(glob("{$root}/src/*.php"), glob("{$root}/src/*/*.php"), glob("{$root}/resources/views/*.blade.php")) as $file) {
        $source .= file_get_contents($file);
    }

    foreach (['sort', 'quick-filters', 'columns'] as $group) {
        foreach (flattenKeys(require "{$root}/resources/lang/en/{$group}.php") as $key) {
            $parent = str_contains($key, '.') ? substr($key, 0, strrpos($key, '.')) : null;
            $used = str_contains($source, "table-views::{$group}.{$key}")
                || ($parent !== null && (str_contains($source, "table-views::{$group}.{$parent}.'") || str_contains($source, "table-views::{$group}.{$parent}.\"")));

            expect($used)->toBeTrue("{$group}.{$key} is never used");
        }
    }

    preg_match_all("/'(table-views::(?:sort|quick-filters|columns)\\.[a-z-]+)' =>/", file_get_contents("{$root}/src/TableViewsServiceProvider.php"), $aliases);

    expect($aliases[1])->not->toBeEmpty();

    foreach ($aliases[1] as $alias) {
        expect(str_contains(file_get_contents("{$root}/resources/views/sort-button.blade.php").file_get_contents("{$root}/resources/views/quick-filters.blade.php").file_get_contents("{$root}/resources/views/columns-list.blade.php").file_get_contents("{$root}/resources/views/columns-fallback.blade.php"), "'{$alias}'"))->toBeTrue("{$alias} is never resolved");
    }
});
