<?php

namespace App\Contracts\Repositories;

use App\Enums\UserRole;
use App\Models\StationHierarchy;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface StationHierarchyRepositoryInterface
{
    public function allWithRelations(): Collection;

    public function findForStation(int $stationId): ?StationHierarchy;

    public function enabledUsersForRole(UserRole $role): Collection;

    public function reportingRelationships(): Collection;

    public function findEnabledUser(int $userId): ?User;

    public function updateReportingRelationship(User $subordinate, User $supervisor): void;

    public function findSupervisorFor(User $subordinate): ?User;

    public function syncStationManager(User $user, array $stationIds): void;

    public function userHasPendingApproval(User $user): bool;
}
