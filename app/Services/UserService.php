<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class UserService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private StationHierarchyService $hierarchies,
        private RoleService $roles,
    ) {}

    public function paginate(string $role, ?string $search, ?string $status): LengthAwarePaginator
    {
        $enabled = match ($status) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        return $this->users->paginateByRole($role, $search, $enabled);
    }

    /** @return array<string, array{total: int, enabled: int, disabled: int}> */
    public function managedRoleCounts(array $roles): array
    {
        return $this->users->managedRoleCounts($roles);
    }

    public function enabledForRole(UserRole $role): Collection
    {
        return $this->users->enabledForRole($role->value);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $roleName = $data['role'];
            $role = UserRole::tryFrom($roleName);
            $attributes = $this->attributesForRole($data, $role, $roleName);
            $attributes['email_verified_at'] = now();

            $user = $this->users->create($attributes);
            $user->syncRoles([$roleName]);
            if ($role === UserRole::StationManager) {
                $this->hierarchies->syncStationManager($user, $data['station_ids']);
            }

            return $user;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            if ($user->roles()->value('name') !== $data['role']) {
                $this->hierarchies->ensureUserCanBeChanged($user);
            }
            $roleName = $data['role'];
            $role = UserRole::tryFrom($roleName);
            $attributes = $this->attributesForRole($data, $role, $roleName);

            if (blank($attributes['password'] ?? null)) {
                unset($attributes['password']);
            }

            $user = $this->users->update($user, $attributes);
            $user->syncRoles([$roleName]);
            $this->hierarchies->syncStationManager($user, $role === UserRole::StationManager ? $data['station_ids'] : []);

            return $user;
        });
    }

    public function setEnabled(User $user, bool $enabled): User
    {
        if (! $enabled) {
            $this->hierarchies->ensureUserCanBeChanged($user);
        }

        return $this->users->setEnabled($user, $enabled);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesForRole(array $data, ?UserRole $role, string $roleName): array
    {
        $attributes = Arr::except($data, ['role', 'line_id', 'station_ids', 'password_confirmation']);

        if (! $this->roles->requiresDirectStationAssignment($roleName)) {
            $attributes['station_id'] = null;
        }

        if ($role === UserRole::Station) {
            $attributes['employee_number'] = $attributes['employee_number'] ?: null;
        }

        return $attributes;
    }
}
