<?php

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

it('renders arabic labels when the locale is ar', function () {
    app()->setLocale('ar');

    expect(__('table-views::favorites-bar.default'))->toBe('الافتراضي')
        ->and(__('table-views::quick-save.fields.name'))->toBe('الاسم');
});

it('uses every translation key and every icon alias', function () {
    $root = __DIR__.'/../..';
    $source = '';

    foreach (array_merge(glob("{$root}/src/*.php"), glob("{$root}/src/*/*.php"), glob("{$root}/resources/views/*.blade.php")) as $file) {
        $source .= file_get_contents($file);
    }

    foreach (['favorites-bar', 'quick-save', 'validation'] as $group) {
        foreach (flattenKeys(require "{$root}/resources/lang/en/{$group}.php") as $key) {
            expect(str_contains($source, "table-views::{$group}.{$key}"))->toBeTrue("{$group}.{$key} is never used");
        }
    }

    preg_match_all("/'(table-views::(?!icons\\.)[a-z.-]+)' =>/", file_get_contents("{$root}/src/TableViewsServiceProvider.php"), $aliases);

    expect($aliases[1])->not->toBeEmpty();

    foreach ($aliases[1] as $alias) {
        expect(substr_count($source, "'{$alias}'"))->toBeGreaterThan(1, "{$alias} is never resolved");
    }
});
