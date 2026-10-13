<?php

namespace TableKit\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

class ScopedOrder extends Order
{
    protected $table = 'orders';

    protected static function booted(): void
    {
        static::addGlobalScope('not-cancelled', fn (Builder $query) => $query->where('status', '!=', 'cancelled'));
    }
}
