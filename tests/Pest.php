<?php

use Elite\TableViews\Models\TableView;
use Elite\TableViews\Support\TableViewState;
use Elite\TableViews\Tests\Fixtures\Pages\ListOrders;
use Elite\TableViews\Tests\TestCase;
use Livewire\Features\SupportTesting\Testable;

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
