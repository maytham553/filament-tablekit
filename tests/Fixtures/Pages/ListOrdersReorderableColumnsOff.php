<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

class ListOrdersReorderableColumnsOff extends ListOrdersReorderableColumns
{
    public static function columnOrderTableViews(): bool
    {
        return false;
    }
}
