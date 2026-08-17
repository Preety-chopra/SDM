<?php

namespace App\Contracts\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginateByRole(UserRole $role, ?string $search, ?bool $enabled): LengthAwarePaginator;

    /** @return array<string, array{total: int, enabled: int, disabled: int}> */
    public function managedRoleCounts(): array;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): User;

    /** @param array<string, mixed> $attributes */
    public function update(User $user, array $attributes): User;

    public function setEnabled(User $user, bool $enabled): User;
}
