<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(UserPermission::ViewUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(UserPermission::CreateUsers->value);
    }

    public function update(User $user, User $managedUser): bool
    {
        return $user->can(UserPermission::UpdateUsers->value)
            && ! $managedUser->hasRole(UserRole::Administrator->value);
    }

    public function changeStatus(User $user, User $managedUser): bool
    {
        return $user->can(UserPermission::ChangeUserStatus->value)
            && ! $managedUser->hasRole(UserRole::Administrator->value);
    }
}
