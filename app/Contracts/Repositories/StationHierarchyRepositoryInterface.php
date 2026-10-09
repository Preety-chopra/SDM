<?php

namespace App\Contracts\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface StationHierarchyRepositoryInterface
{
    public function enabledUsersForRole(UserRole $role): Collection;

    public function reportingRelationships(): Collection;

    public function findEnabledUser(int $userId): ?User;

    public function updateReportingRelationship(User $subordinate, User $supervisor): void;

    public function userHasPendingApproval(User $user): bool;
}
