<?php

namespace Elite\TableViews\Support;

use JsonSerializable;

final class TableViewState implements JsonSerializable
{
    public const VERSION = 1;

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $columnSearches
     * @param  array<string, bool>  $toggledColumns  column name => shown
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

        return new self(
            filters: self::map($data['filters'] ?? null),
            search: is_scalar($data['search'] ?? null) ? (string) $data['search'] : '',
            columnSearches: self::map($data['columnSearches'] ?? null),
            sortColumn: self::string($sort['column'] ?? null),
            sortDirection: self::string($sort['direction'] ?? null),
            toggledColumns: self::map($data['toggledColumns'] ?? null),
            groupingColumn: self::string($grouping['column'] ?? null),
            groupingDirection: self::string($grouping['direction'] ?? null),
            tab: self::string($data['tab'] ?? null),
            version: is_int($data['v'] ?? null) ? $data['v'] : self::VERSION,
        );
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
