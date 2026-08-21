<?php

namespace App\Services;

use App\Contracts\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleService
{
    public function __construct(private RoleRepositoryInterface $roles) {}

    /** @return Collection<int, Role> */
    public function managedRoles(): Collection
    {
        return $this->roles->managedRoles();
    }

    /** @return Collection<int, Permission> */
    public function permissions(): Collection
    {
        return $this->roles->permissions();
    }

    public function findManagedByNameOrFail(string $name): Role
    {
        return $this->roles->findManagedByNameOrFail($name);
    }

    /** @param array{name: string, permissions?: list<string>, new_permissions?: list<string>} $data */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = $this->roles->create($data['name']);

            return $this->syncPermissions($role, $data);
        });
    }

    /** @param array{name: string, permissions?: list<string>, new_permissions?: list<string>} $data */
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $role = $this->roles->update($role, $data['name']);

            return $this->syncPermissions($role, $data);
        });
    }

    /** @param array{permissions?: list<string>, new_permissions?: list<string>} $data */
    private function syncPermissions(Role $role, array $data): Role
    {
        $newPermissions = collect($data['new_permissions'] ?? [])
            ->map(fn (string $name): string => $this->roles->findOrCreatePermission($name)->name);

        $permissions = collect($data['permissions'] ?? [])
            ->merge($newPermissions)
            ->unique()
            ->values()
            ->all();

        return $this->roles->syncPermissions($role, $permissions);
    }
}
