<?php

namespace Elite\TableViews\Support;

use Closure;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use WeakMap;

/**
 * Backs the `favorite()` macro on Filament filters: a flag kept per filter object, so no property is added to Filament's class.
 */
class FavoriteFilters
{
    /** @var WeakMap<BaseFilter, bool|Closure>|null */
    protected static ?WeakMap $flags = null;

    public static function mark(BaseFilter $filter, bool|Closure $condition = true): void
    {
        static::$flags ??= new WeakMap;
        static::$flags[$filter] = $condition;
    }

    public static function isFavorite(BaseFilter $filter): bool
    {
        $flag = static::$flags !== null && isset(static::$flags[$filter]) ? static::$flags[$filter] : false;

        if (! $flag instanceof Closure) {
            return (bool) $flag;
        }

        return (bool) (fn (): mixed => $this->evaluate($flag))->call($filter);
    }

    /**
     * The visible filters the developer marked with `favorite()`, in the order the table defines them.
     *
     * @return array<int, string>
     */
    public static function defaults(Table $table): array
    {
        $names = [];

        foreach ($table->getFilters() as $name => $filter) {
            if (static::isFavorite($filter)) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }
}
