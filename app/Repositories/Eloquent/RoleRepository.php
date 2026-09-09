<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\RoleRepositoryInterface;
use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleRepository implements RoleRepositoryInterface
{
    public function managedRoles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', UserRole::Administrator->value)
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function permissions(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();
    }

    public function findManagedByNameOrFail(string $name): Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', UserRole::Administrator->value)
            ->where('name', $name)
            ->with('permissions')
            ->firstOrFail();
    }

    public function findManagedByName(string $name): ?Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', UserRole::Administrator->value)
            ->where('name', $name)
            ->with('permissions')
            ->first();
    }

    public function create(string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web']);
    }

    public function update(Role $role, string $name): Role
    {
        $role->update(['name' => $name]);

        return $role->refresh();
    }

    public function findOrCreatePermission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }

    public function syncPermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }
}
