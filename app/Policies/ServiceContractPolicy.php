<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Models\ServiceContract;
use App\Models\User;

class ServiceContractPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(UserPermission::ManageContracts->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceContract $serviceContract): bool
    {
        return $user->can(UserPermission::ManageContracts->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(UserPermission::ManageContracts->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceContract $serviceContract): bool
    {
        return $user->can(UserPermission::ManageContracts->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceContract $serviceContract): bool
    {
        return $user->can(UserPermission::ManageContracts->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ServiceContract $serviceContract): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ServiceContract $serviceContract): bool
    {
        return false;
    }
}
