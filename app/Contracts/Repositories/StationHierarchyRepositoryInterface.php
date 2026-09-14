<?php

namespace App\Contracts\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface StationHierarchyRepositoryInterface
{
    public function enabledUsersForRole(UserRole $role): Collection;

    public function findEnabledUserForRoleByEmployeeNumber(UserRole $role, string $employeeNumber): ?User;

    public function reportingRelationships(): Collection;

    public function findEnabledUser(int $userId): ?User;

    public function updateReportingRelationship(User $subordinate, User $supervisor): void;

    public function findSupervisorFor(User $subordinate): ?User;

    public function userHasPendingApproval(User $user): bool;
}
