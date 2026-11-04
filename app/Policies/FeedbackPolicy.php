<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class FeedbackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_enabled && $user->hasRole(UserRole::Administrator->value);
    }

    public function create(User $user): bool
    {
        return $user->is_enabled;
    }
}
