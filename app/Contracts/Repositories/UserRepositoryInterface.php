<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function paginateByRole(string $role, ?string $search, ?bool $enabled): LengthAwarePaginator;

    /** @param list<string> $roles */
    /** @return array<string, array{total: int, enabled: int, disabled: int}> */
    public function managedRoleCounts(array $roles): array;

    public function enabledForRole(string $role): Collection;

    public function findEnabledForRoleByEmployeeNumber(string $role, string $employeeNumber): ?User;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): User;

    /** @param array<string, mixed> $attributes */
    public function update(User $user, array $attributes): User;

    public function setEnabled(User $user, bool $enabled): User;

    public function setLoginToken(User $user, string $token): bool;

    public function clearLoginToken(User $user): void;

    public function acknowledgeFeedback(User $user, int $feedbackId): bool;
}
