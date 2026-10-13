<?php

namespace TableKit\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class OtherUser extends Authenticatable implements FilamentUser
{
    protected $table = 'other_users';

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
