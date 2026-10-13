<?php

namespace TableKit\Support;

/**
 * Checks an order sent by the browser after a drag: it must be a list holding exactly the keys the user may reorder, each once.
 */
final class ReorderedKeys
{
    /**
     * @param  array<int, string>  $expected
     * @return array<int, string>|null the submitted keys as strings, or null when they are not a permutation of $expected
     */
    public static function validate(mixed $submitted, array $expected): ?array
    {
        if (! is_array($submitted) || count($submitted) !== count($expected) || ! array_is_list($submitted)) {
            return null;
        }

        $keys = [];

        foreach ($submitted as $key) {
            if (! is_string($key) && ! is_int($key)) {
                return null;
            }

            $keys[] = (string) $key;
        }

        $expected = array_map('strval', $expected);

        if (count(array_unique($keys)) !== count($keys) || array_diff($keys, $expected) !== [] || array_diff($expected, $keys) !== []) {
            return null;
        }

        return $keys;
    }

    /**
     * Puts $ordered into the slots that $subset holds inside $full, leaving every other entry where it is.
     *
     * @param  array<int, string>  $full
     * @param  array<int, string>  $subset
     * @param  array<int, string>  $ordered
     * @return array<int, string>
     */
    public static function mergeInto(array $full, array $subset, array $ordered): array
    {
        $queue = $ordered;

        foreach ($full as $index => $key) {
            if (in_array($key, $subset, true)) {
                $full[$index] = array_shift($queue);
            }
        }

        return $full;
    }
}
