<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /** @return Collection<int, Role> */
    public function managedRoles(): Collection;

    /** @return Collection<int, Permission> */
    public function permissions(): Collection;

    public function findManagedByName(string $name): ?Role;

    public function findManagedByNameOrFail(string $name): Role;

    public function create(string $name): Role;

    public function update(Role $role, string $name): Role;

    public function findOrCreatePermission(string $name): Permission;

    /** @param list<string> $permissions */
    public function syncPermissions(Role $role, array $permissions): Role;
}
