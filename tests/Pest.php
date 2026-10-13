<?php

use Livewire\Features\SupportTesting\Testable;
use TableKit\Models\TablePreference;
use TableKit\Models\TableView;
use TableKit\Support\TableViewState;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Presets;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;
use TableKit\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');

/**
 * Saves the component's current table state through the real Quick Save action.
 */
function quickSave(Testable $component, string $name = 'My view', array $data = []): TableView
{
    $component
        ->callAction('quickSaveTableView', ['name' => $name, 'color' => 'primary', ...$data])
        ->assertHasNoActionErrors();

    return TableView::query()->where('name', $name)->latest('id')->firstOrFail();
}

/**
 * Creates a stored view directly, independent of the capture code path.
 */
function storeView(int|string $userId, array $state, array $attributes = []): TableView
{
    return TableView::create([
        'user_id' => $userId,
        'name' => 'Stored '.uniqid(),
        'resource' => ListOrders::class,
        'state' => TableViewState::fromArray($state),
        ...$attributes,
    ]);
}

/**
 * Calls a protected method on the component behind a Livewire test.
 */
function callProtected(Testable $component, string $method, mixed ...$arguments): mixed
{
    $instance = $component->instance();

    return (new ReflectionMethod($instance, $method))->invoke($instance, ...$arguments);
}

/**
 * Sets the presets returned by the ListOrdersWithPresets fixture (and its defaults variant).
 */
function presets(array|Closure $presets): void
{
    ListOrdersWithPresets::$presets = $presets;
}

/**
 * Sets the presets returned by the ListOrdersPhase3Presets fixture.
 */
function phase3Presets(array|Closure $presets): void
{
    ListOrdersPhase3Presets::$presets = $presets;
}

/**
 * Creates a preference row directly, independent of the manager code path.
 */
function storePreference(int|string $userId, string $type, string $key, array $attributes = []): TablePreference
{
    return TablePreference::create([
        'user_id' => $userId,
        'resource' => ListOrdersWithPresets::class,
        'view_type' => $type,
        'view_key' => $key,
        ...$attributes,
    ]);
}

/**
 * The references in the order the table query returns them, ignoring pagination.
 *
 * @return array<int, string>
 */
function queryReferences(Testable $component): array
{
    return $component->instance()->getFilteredSortedTableQuery()->pluck('reference')->all();
}

/**
 * What the database order should be for a status rank, an amount and the reference as the last tie-break (the rank mirrors the fixture's sort closure).
 *
 * @param  array<int, array{0: string, 1: string}>  $keys  [field, direction] pairs, field = rank|amount|reference|customer_name|id
 * @return array<int, string>
 */
function expectedReferences(array $keys): array
{
    $rank = ['paid' => 1, 'shipped' => 2, 'pending' => 3];

    return Order::query()->get()
        ->sort(function ($a, $b) use ($keys, $rank): int {
            foreach ($keys as [$field, $direction]) {
                $valueA = $field === 'rank' ? ($rank[$a->status] ?? 4) : $a->{$field};
                $valueB = $field === 'rank' ? ($rank[$b->status] ?? 4) : $b->{$field};
                $result = $valueA <=> $valueB;

                if ($result !== 0) {
                    return $direction === 'desc' ? -$result : $result;
                }
            }

            return 0;
        })
        ->pluck('reference')
        ->values()
        ->all();
}

/**
 * One column of the rows in the order the table query returns them, ignoring pagination.
 *
 * @return array<int, mixed>
 */
function queryColumn(Testable $component, string $column): array
{
    return $component->instance()->getFilteredSortedTableQuery()->pluck($column)->all();
}

function isSortedAscending(array $values): bool
{
    $sorted = $values;
    sort($sorted);

    return $sorted === $values;
}

/**
 * The browser side of Livewire's #[Url] (dist/livewire.js: track + queryStringUtils), reduced to what decides the final query string:
 * a parameter that was not in the page URL is removed when its value equals the value after mount (the seed) or equals `except`,
 * every other value is written, `alwaysShow` writes it whatever it is. Nulls become `key=`, nulls inside arrays and empty arrays vanish.
 */
function browserUrl(Testable $mounted, array $pageQuery = []): Closure
{
    $entries = $mounted->effects['url'] ?? [];
    $seeds = array_map(fn ($entry, $name) => $mounted->get($name), $entries, array_keys($entries));
    $seeds = array_combine(array_keys($entries), $seeds);

    $strip = function (mixed $value) use (&$strip): mixed {
        if (! is_array($value)) {
            return is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        return array_filter(array_map($strip, $value), fn ($item) => $item !== null && $item !== []);
    };

    return function (Testable $current) use ($entries, $seeds, $pageQuery, $strip): array {
        $query = $pageQuery;

        foreach ($entries as $name => $entry) {
            $as = $entry['as'] ?? $name;
            $present = array_key_exists($as, $pageQuery);
            $value = $current->get($name);
            $always = (bool) ($entry['alwaysShow'] ?? false);
            $except = $entry['except'] ?? null;

            $dropped = (! $always && ! $present && json_encode($value) === json_encode($seeds[$name]))
                || (! $always && json_encode($value) === json_encode($except));

            if ($dropped) {
                unset($query[$as]);

                continue;
            }

            $query[$as] = $strip($value) ?? '';
        }

        parse_str(urldecode(http_build_query(array_filter($query, fn ($v) => $v !== []))), $parsed);

        return $parsed;
    };
}

/**
 * The markup of one chip, from its marker to the next chip (or the end of the bar).
 */
function chipHtml(string $html, string $name): string
{
    $start = strpos($html, 'data-quick-filter="'.$name.'"');

    if ($start === false) {
        return '';
    }

    $next = strpos($html, 'data-quick-filter="', $start + 10);

    return substr($html, $start, ($next === false ? strlen($html) : $next) - $start);
}

/**
 * The filter-state wire:model bindings inside a chip's markup (its popover): path => modifier ('.live' or '').
 *
 * @return array<string, string>
 */
function popoverBindings(string $html, string $name): array
{
    preg_match_all('/wire:model(\.[a-z.]+)?="((?:tableViewQuickFilterState|tableFilters|tableDeferredFilters)[^"]*)"/', chipHtml($html, $name), $matches);

    return array_combine($matches[2], $matches[1]);
}

/**
 * Values that occur more than once for an attribute (`id`, `wire:key`) in the rendered HTML.
 *
 * @return array<int, string>
 */
function duplicateAttributeValues(string $html, string $attribute): array
{
    preg_match_all('/\s'.preg_quote($attribute, '/').'="([^"]+)"/', $html, $matches);

    return array_keys(array_filter(array_count_values($matches[1]), fn (int $count): bool => $count > 1));
}

function chipNames(string $html): array
{
    preg_match_all('/data-quick-filter="([^"]+)"/', $html, $matches);

    return $matches[1];
}

/**
 * The column names of the table header, left to right.
 *
 * @return array<int, string>
 */
function headerOrder(string $html): array
{
    preg_match_all('/<th[^>]*fi-table-header-cell-([a-z0-9-]+)/', $html, $matches);

    return $matches[1];
}

/**
 * The column names of the first body row, left to right.
 *
 * @return array<int, string>
 */
function firstRowOrder(string $html): array
{
    preg_match_all('/<td[^>]*fi-table-cell-([a-z0-9-]+)/', $html, $matches);

    $seen = [];

    foreach ($matches[1] as $name) {
        if (in_array($name, $seen, true)) {
            break;
        }

        $seen[] = $name;
    }

    return $seen;
}

const CODE_HEADERS = ['reference', 'customer-name', 'status', 'amount', 'team', 'ordered-at', 'plain'];
