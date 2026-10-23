<?php

namespace App\Support;

use App\Enums\UserPermission;
use Illuminate\Support\Str;

final class PermissionLabel
{
    public static function for(string $permission): string
    {
        $knownPermission = UserPermission::tryFrom($permission);

        if ($knownPermission !== null) {
            return $knownPermission->label();
        }

        $segments = explode('.', $permission);
        $action = Str::of((string) array_pop($segments))->replace('_', ' ')->headline();
        $subject = Str::of(implode(' ', $segments))->replace('_', ' ')->headline()->lower();

        return trim("{$action} {$subject}");
    }
}
