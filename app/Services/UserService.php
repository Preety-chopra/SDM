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
        private AuditService $audit,
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
    public function create(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $roleName = $data['role'];
            $role = UserRole::tryFrom($roleName);
            $attributes = $this->attributesForRole($data, $role, $roleName);
            $attributes['email_verified_at'] = now();

            $user = $this->users->create($attributes);
            $user->syncRoles([$roleName]);
            $this->audit->record($user, $actor, 'user.created', after: $this->auditValues($user));

            return $user;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $before = $this->auditValues($user);
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
            $this->audit->record($user, $actor, 'user.updated', $before, [
                ...$this->auditValues($user->unsetRelation('roles')),
                'password_changed' => filled($data['password'] ?? null),
            ]);

            return $user;
        });
    }

    public function setEnabled(User $user, bool $enabled, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $enabled, $actor): User {
            if (! $enabled) {
                $this->hierarchies->ensureUserCanBeChanged($user);
            }

            $before = ['is_enabled' => $user->is_enabled];
            $user = $this->users->setEnabled($user, $enabled);
            $this->audit->record($user, $actor, $enabled ? 'user.enabled' : 'user.disabled', $before, ['is_enabled' => $enabled]);

            return $user;
        });
    }

    private function auditValues(User $user): array
    {
        return [
            ...$user->only(['name', 'employee_number', 'designation', 'station_id', 'is_enabled']),
            'roles' => $user->getRoleNames()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesForRole(array $data, ?UserRole $role, string $roleName): array
    {
        $attributes = Arr::except($data, ['role', 'line_id', 'password_confirmation']);

        if (! $this->roles->requiresDirectStationAssignment($roleName)) {
            $attributes['station_id'] = null;
        }

        if ($role === UserRole::Station) {
            $attributes['employee_number'] = $attributes['employee_number'] ?: null;
        }

        return $attributes;
    }
}
