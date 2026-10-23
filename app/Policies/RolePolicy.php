<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(UserPermission::ManageRoles->value);
    }

    public function create(User $user): bool
    {
        return $user->can(UserPermission::ManageRoles->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(UserPermission::ManageRoles->value)
            && $role->name !== UserRole::Administrator->value;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(UserPermission::ManageRoles->value)
            && $role->name !== UserRole::Administrator->value;
    }
}
