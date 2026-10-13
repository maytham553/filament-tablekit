<?php

use Livewire\Features\SupportTesting\Testable;
use TableKit\Models\TablePreference;
use TableKit\Models\TableView;
use TableKit\Support\TableViewState;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrders;
use TableKit\Tests\Fixtures\Pages\ListOrdersPhase3Presets;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;
use TableKit\Tests\Fixtures\User;
use TableKit\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');

/**
 * Saves the component's current table state through the real Quick Save action.
 */
function quickSave(Testable $component, string $name = 'My view', array $data = []): TableView
{
    closeOpenAction($component)
        ->callAction('quickSaveTableView', ['name' => $name, 'color' => 'primary', ...$data])
        ->assertHasNoActionErrors();

    return TableView::query()->where('name', $name)->latest('id')->firstOrFail();
}

/**
 * A modal that failed validation stays open, and the next call would nest inside it.
 */
function closeOpenAction(Testable $component): Testable
{
    return $component->get('mountedActions') === [] ? $component : $component->call('unmountAction');
}

/**
 * What the browser would have after the last request: Filament sends only the changed action modal as a partial while an action is mounted,
 * and the whole page otherwise.
 */
function renderedHtml(Testable $component): string
{
    $partials = $component->effects['partials'] ?? [];

    return $partials === [] ? $component->html() : implode("\n", $partials);
}

/**
 * Creates a stored view directly, independent of the capture code path.
 */
function storeView(int|string $userId, array $state, array $attributes = []): TableView
{
    return TableView::create([
        'user_type' => User::class,
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
 * Creates a preference row directly, independent of the manager code path.
 */
function storePreference(int|string $userId, string $type, string $key, array $attributes = []): TablePreference
{
    return TablePreference::create([
        'user_type' => User::class,
        'user_id' => $userId,
        'resource' => ListOrdersWithPresets::class,
        'view_type' => $type,
        'view_key' => $key,
        ...$attributes,
    ]);
}

/**
 * Shown (true) or hidden (false) state of every toggleable column, by column name, as the column manager holds it.
 *
 * @return array<string, bool>
 */
function toggles(Testable $component): array
{
    $flatten = function (array $items) use (&$flatten): array {
        $result = [];

        foreach ($items as $item) {
            if ($item['type'] === 'group') {
                $result = [...$result, ...$flatten($item['columns'])];
            } elseif ($item['isToggleable']) {
                $result[$item['name']] = $item['isToggled'];
            }
        }

        return $result;
    };

    return $flatten($component->get('tableColumns'));
}

/**
 * Does what the column manager does in the browser: sends the whole column list back with the given columns shown or hidden.
 *
 * @param  array<string, bool>  $shown
 */
function setToggles(Testable $component, array $shown): Testable
{
    $apply = function (array $items) use (&$apply, $shown): array {
        return array_map(function (array $item) use (&$apply, $shown): array {
            if ($item['type'] === 'group') {
                $item['columns'] = $apply($item['columns']);
            } elseif (array_key_exists($item['name'], $shown)) {
                $item['isToggled'] = $shown[$item['name']];
            }

            return $item;
        }, $items);
    };

    return $component->call('applyTableColumnManager', $apply($component->get('tableColumns')));
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
 * Sets the presets returned by the ListOrdersPhase3Presets fixture.
 */
function phase3Presets(array|Closure $presets): void
{
    ListOrdersPhase3Presets::$presets = $presets;
}

function sortColumnOf(Testable $component): ?string
{
    return $component->instance()->getTableSortColumn();
}

function sortDirectionOf(Testable $component): ?string
{
    return $component->instance()->getTableSortDirection();
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
 * The markup of one chip, from its marker to the next chip (or the end of the header).
 */
function chipHtml(string $html, string $name): string
{
    $start = strpos($html, 'data-quick-filter="'.$name.'"');

    if ($start === false) {
        return '';
    }

    $next = strpos($html, 'data-quick-filter="', $start + 10);
    $end = strpos($html, 'wire:loading.attr="aria-busy"', $start);
    $next = $next === false ? $end : ($end === false ? $next : min($next, $end));

    return substr($html, $start, ($next === false ? strlen($html) : $next) - $start);
}

/**
 * The filter-state bindings (wire:model, or $wire.$entangle for the JS select) inside a chip's markup (its popover): path => modifier ('.live' or '').
 *
 * @return array<string, string>
 */
function popoverBindings(string $html, string $name): array
{
    preg_match_all('/wire:model(\.[a-z.]+)?="((?:tableViewQuickFilterState|tableFilters|tableDeferredFilters)[^"]*)"/', chipHtml($html, $name), $matches);

    $bindings = array_combine($matches[2], $matches[1]);

    preg_match_all('/\$entangle\(\'((?:tableViewQuickFilterState|tableFilters|tableDeferredFilters)[^\']*)\', (true|false)\)/', chipHtml($html, $name), $entangled);

    foreach ($entangled[1] as $index => $path) {
        $bindings[$path] ??= $entangled[2][$index] === 'true' ? '.live' : '';
    }

    return $bindings;
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
    preg_match_all('/<th[^>]*?fi-ta-header-cell-(?!sorted)([a-z0-9-]+)/', $html, $matches);

    return $matches[1];
}

/**
 * Column names in the order of the column manager state.
 *
 * @return array<int, string>
 */
function managerOrder(Testable $component): array
{
    $names = function (array $items) use (&$names): array {
        return array_merge(...array_map(fn (array $item): array => $item['type'] === 'group' ? $names($item['columns']) : [$item['name']], $items));
    };

    return $names($component->get('tableColumns'));
}

/**
 * Does what the column manager does when the user drags a column: sends the whole list back in the given name order (a group moves with its columns).
 *
 * @param  array<int, string>  $order
 */
function reorderColumns(Testable $component, array $order): Testable
{
    $items = $component->get('tableColumns');
    $score = fn (array $item): int => $item['type'] === 'group'
        ? min(array_map(fn (array $column): int => (int) array_search($column['name'], $order, true), $item['columns']))
        : (int) array_search($item['name'], $order, true);

    usort($items, fn (array $a, array $b): int => $score($a) <=> $score($b));

    $items = array_map(function (array $item) use ($order): array {
        if ($item['type'] === 'group') {
            usort($item['columns'], fn (array $a, array $b): int => array_search($a['name'], $order, true) <=> array_search($b['name'], $order, true));
        }

        return $item;
    }, $items);

    return $component->call('applyTableColumnManager', $items, true);
}
