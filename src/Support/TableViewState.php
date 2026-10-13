<?php

namespace TableKit\Support;

use JsonSerializable;

final class TableViewState implements JsonSerializable
{
    public const VERSION = 1;

    private const MAX_NAMES = 200;

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $columnSearches
     * @param  array<string, mixed>  $toggledColumns
     * @param  array<int, array{column: string, direction: string}>  $sorts  the whole multi-sort, primary first; sortColumn/sortDirection are its first entry
     * @param  array<int, string>  $columnOrder  column names, empty = the order defined in code
     * @param  array<int, string>|null  $favoriteFilters  null = the developer's favorite filters
     */
    public function __construct(
        public readonly array $filters = [],
        public readonly string $search = '',
        public readonly array $columnSearches = [],
        public readonly ?string $sortColumn = null,
        public readonly ?string $sortDirection = null,
        public readonly array $toggledColumns = [],
        public readonly ?string $groupingColumn = null,
        public readonly ?string $groupingDirection = null,
        public readonly ?string $tab = null,
        public readonly int $version = self::VERSION,
        public readonly array $sorts = [],
        public readonly array $columnOrder = [],
        public readonly ?array $favoriteFilters = null,
    ) {}

    /**
     * Tolerant reader: missing keys fall back to empty values, unknown keys are ignored.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $data = self::stripNul($data);

        $sort = is_array($data['sort'] ?? null) ? $data['sort'] : [];
        $grouping = is_array($data['grouping'] ?? null) ? $data['grouping'] : [];
        $sorts = self::sortList($data['sorts'] ?? null);

        return new self(
            filters: self::map($data['filters'] ?? null),
            search: is_scalar($data['search'] ?? null) ? (string) $data['search'] : '',
            columnSearches: self::map($data['columnSearches'] ?? null),
            sortColumn: $sorts !== [] ? $sorts[0]['column'] : self::string($sort['column'] ?? null),
            sortDirection: $sorts !== [] ? $sorts[0]['direction'] : self::string($sort['direction'] ?? null),
            toggledColumns: self::map($data['toggledColumns'] ?? null),
            groupingColumn: self::string($grouping['column'] ?? null),
            groupingDirection: self::string($grouping['direction'] ?? null),
            tab: self::string($data['tab'] ?? null),
            version: is_int($data['v'] ?? null) ? $data['v'] : self::VERSION,
            sorts: $sorts,
            columnOrder: self::names($data['columnOrder'] ?? null, self::MAX_NAMES) ?? [],
            favoriteFilters: self::names($data['favoriteFilters'] ?? null, self::MAX_NAMES),
        );
    }

    /**
     * The sorts in priority order. A view saved before multi-sort has only `sort`, which is the one-entry list.
     *
     * @return array<int, array{column: string, direction: string}>
     */
    public function sortEntries(): array
    {
        if ($this->sorts !== []) {
            return $this->sorts;
        }

        return $this->sortColumn === null ? [] : [['column' => $this->sortColumn, 'direction' => $this->sortDirection === 'desc' ? 'desc' : 'asc']];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'v' => $this->version,
            'filters' => $this->filters,
            'search' => $this->search,
            'columnSearches' => $this->columnSearches,
            'sort' => ['column' => $this->sortColumn, 'direction' => $this->sortDirection],
            'toggledColumns' => $this->toggledColumns,
            'grouping' => ['column' => $this->groupingColumn, 'direction' => $this->groupingDirection],
        ];

        if (count($this->sorts) > 1) {
            $data['sorts'] = $this->sorts;
        }

        if ($this->columnOrder !== []) {
            $data['columnOrder'] = $this->columnOrder;
        }

        if ($this->favoriteFilters !== null) {
            $data['favoriteFilters'] = $this->favoriteFilters;
        }

        if ($this->tab !== null) {
            $data['tab'] = $this->tab;
        }

        return $data;
    }

    /**
     * Empty maps become objects so they are stored as `{}` and not `[]`.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = $this->toArray();

        foreach (['filters', 'columnSearches', 'toggledColumns'] as $key) {
            if ($data[$key] === []) {
                $data[$key] = (object) [];
            }
        }

        return $data;
    }

    /**
     * The exact bytes that get stored.
     */
    public function toJson(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    public function hash(): string
    {
        return md5((string) json_encode(self::sortKeys($this->toArray()), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return array<int, array{column: string, direction: string}>
     */
    private static function sortList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $entry) {
            $column = is_array($entry) ? self::string($entry['column'] ?? null) : null;

            if ($column === null || strlen($column) > 255 || isset($list[$column])) {
                continue;
            }

            $list[$column] = ['column' => $column, 'direction' => ($entry['direction'] ?? null) === 'desc' ? 'desc' : 'asc'];

            if (count($list) >= self::MAX_NAMES) {
                break;
            }
        }

        return array_values($list);
    }

    /**
     * @return array<int, string>|null null when the value is not a list at all
     */
    private static function names(mixed $value, int $limit): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $names = [];

        foreach ($value as $name) {
            if (is_string($name) && $name !== '' && strlen($name) <= 255) {
                $names[$name] = true;
            }

            if (count($names) >= $limit) {
                break;
            }
        }

        return array_map('strval', array_keys($names));
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * jsonb rejects U+0000, and a client can put one in a search or filter value.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function stripNul(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $key = str_replace("\0", '', $key);
            }

            $clean[$key] = match (true) {
                is_array($value) => self::stripNul($value),
                is_string($value) => str_replace("\0", '', $value),
                default => $value,
            };
        }

        return $clean;
    }

    private static function sortKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortKeys($value);
            }
        }

        ksort($data);

        return $data;
    }
}
