<?php

namespace App\Services;

use App\Contracts\Repositories\RoleRepositoryInterface;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $roles,
        private AuditService $audit,
    ) {}

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

    public function requiresDirectStationAssignment(string $name): bool
    {
        $role = $this->roles->findManagedByName($name);

        return $role !== null && $this->roleRequiresDirectStationAssignment($role);
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @return list<string>
     */
    public function directStationRoleNames(Collection $roles): array
    {
        return $roles
            ->filter(fn (Role $role): bool => $this->roleRequiresDirectStationAssignment($role))
            ->pluck('name')
            ->values()
            ->all();
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

    public function delete(Role $role, User $actor): void
    {
        DB::transaction(function () use ($role, $actor): void {
            if ($this->roles->hasUsers($role)) {
                throw ValidationException::withMessages([
                    'role' => 'Remove all users from this role before deleting it.',
                ]);
            }

            $role->loadMissing('permissions');
            $this->audit->record($role, $actor, 'role.deleted', before: [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ]);
            $this->roles->delete($role);
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

    private function roleRequiresDirectStationAssignment(Role $role): bool
    {
        return $role->name !== UserRole::StationManager->value
            && $role->permissions->contains('name', UserPermission::CreateMemos->value);
    }
}
