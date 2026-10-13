<?php

namespace TableKit\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class CurrentContext
{
    public static function userId(): int|string|null
    {
        return Filament::getCurrentPanel() ? Filament::auth()->id() : auth()->id();
    }

    public static function userType(): ?string
    {
        $user = Filament::getCurrentPanel() ? Filament::auth()->user() : auth()->user();

        return match (true) {
            $user instanceof Model => $user->getMorphClass(),
            $user !== null => $user::class,
            default => null,
        };
    }

    public static function tenant(): ?Model
    {
        return Filament::getCurrentPanel() ? Filament::getTenant() : null;
    }

    public static function tenantType(): ?string
    {
        return static::tenant()?->getMorphClass();
    }

    public static function tenantId(): ?string
    {
        $tenant = static::tenant();

        return $tenant ? (string) $tenant->getKey() : null;
    }
}
