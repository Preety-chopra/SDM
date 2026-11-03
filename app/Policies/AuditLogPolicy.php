<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_enabled && $user->hasRole(UserRole::Administrator->value);
    }
}
