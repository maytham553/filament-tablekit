<?php

namespace Elite\TableViews\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasUlids;

    protected $guarded = [];
}
