<?php

namespace TableKit\Tests\Fixtures\Pages;

class ListOrdersPhase3Off extends ListOrdersPhase3
{
    public static function multiSortTableViews(): bool
    {
        return false;
    }

    public static function quickFiltersTableViews(): bool
    {
        return false;
    }

    public static function columnOrderTableViews(): bool
    {
        return false;
    }
}
