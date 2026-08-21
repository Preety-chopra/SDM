<?php

namespace App\Support;

use App\Enums\UserRole;
use Illuminate\Support\Str;

final class RoleLabel
{
    public static function singular(string $role): string
    {
        return UserRole::tryFrom($role)?->label() ?? Str::headline($role);
    }

    public static function plural(string $role): string
    {
        return UserRole::tryFrom($role)?->pluralLabel() ?? self::singular($role).' Users';
    }
}
