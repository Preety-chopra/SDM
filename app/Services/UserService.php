<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UserService
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function paginate(UserRole $role, ?string $search, ?string $status): LengthAwarePaginator
    {
        $enabled = match ($status) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        return $this->users->paginateByRole($role, $search, $enabled);
    }

    /** @return array<string, array{total: int, enabled: int, disabled: int}> */
    public function managedRoleCounts(): array
    {
        return $this->users->managedRoleCounts();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $role = UserRole::from($data['role']);
            $attributes = $this->attributesForRole($data, $role);
            $attributes['email_verified_at'] = now();

            $user = $this->users->create($attributes);
            $user->syncRoles([$role->value]);

            return $user;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $role = UserRole::from($data['role']);
            $attributes = $this->attributesForRole($data, $role);

            if (blank($attributes['password'] ?? null)) {
                unset($attributes['password']);
            }

            $user = $this->users->update($user, $attributes);
            $user->syncRoles([$role->value]);

            return $user;
        });
    }

    public function setEnabled(User $user, bool $enabled): User
    {
        return $this->users->setEnabled($user, $enabled);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesForRole(array $data, UserRole $role): array
    {
        $attributes = Arr::except($data, ['role', 'password_confirmation']);

        if (! $role->requiresStation()) {
            $attributes['station_id'] = null;
        }

        if ($role === UserRole::Station) {
            $attributes['employee_number'] = $attributes['employee_number'] ?: null;
        }

        return $attributes;
    }
}
