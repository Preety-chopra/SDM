<?php

namespace App\Services;

use App\Contracts\Repositories\StationHierarchyRepositoryInterface;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class StationHierarchyService
{
    public function __construct(private StationHierarchyRepositoryInterface $hierarchies) {}

    public function all(): Collection
    {
        return $this->hierarchies->allWithRelations();
    }

    /** @return array<int, array{subordinateRole: UserRole, supervisorRole: UserRole, subordinates: Collection, supervisors: Collection}> */
    public function mappingLevels(): array
    {
        return collect($this->supervisorRoles())
            ->map(fn (UserRole $supervisorRole, string $subordinateRole): array => [
                'subordinateRole' => UserRole::from($subordinateRole),
                'supervisorRole' => $supervisorRole,
                'subordinates' => $this->hierarchies->enabledUsersForRole(UserRole::from($subordinateRole)),
                'supervisors' => $this->hierarchies->enabledUsersForRole($supervisorRole),
            ])
            ->values()
            ->all();
    }

    public function reportingRelationships(): Collection
    {
        return $this->hierarchies->reportingRelationships();
    }

    public function updateReportingRelationship(int $subordinateId, int $supervisorId): void
    {
        $subordinate = $this->hierarchies->findEnabledUser($subordinateId);
        $supervisor = $this->hierarchies->findEnabledUser($supervisorId);
        $subordinateRole = collect(array_keys($this->supervisorRoles()))
            ->map(fn (string $role): UserRole => UserRole::from($role))
            ->first(fn (UserRole $role): bool => $subordinate?->hasRole($role->value));
        $expectedSupervisorRole = $subordinateRole ? $this->supervisorRoles()[$subordinateRole->value] : null;

        if (! $subordinate || ! $supervisor || ! $expectedSupervisorRole || ! $supervisor->hasRole($expectedSupervisorRole->value)) {
            throw ValidationException::withMessages([
                'supervisor_id' => 'Select an enabled immediate supervisor from the next approval level.',
            ]);
        }

        $this->hierarchies->updateReportingRelationship($subordinate, $supervisor);
    }

    /** @return array<string, User> */
    public function resolveForSubmission(User $creator, ?string $stationManagerEmployeeNumber = null): array
    {
        return $this->resolveForStation((int) $creator->station_id, $stationManagerEmployeeNumber);
    }

    /** @return array<string, User> */
    public function resolveForStation(int $stationId, ?string $stationManagerEmployeeNumber = null): array
    {
        $hierarchy = $stationId > 0 ? $this->hierarchies->findForStation($stationId) : null;
        $assignee = $hierarchy?->stationManager;
        $assignments = [];

        if (filled($stationManagerEmployeeNumber) && $assignee?->employee_number !== $stationManagerEmployeeNumber) {
            throw ValidationException::withMessages([
                'station_manager_employee_number' => 'Enter the employee number of the enabled Station Manager assigned to this station.',
            ]);
        }

        foreach (WorkflowStage::cases() as $stage) {
            if (! $assignee?->is_enabled || ! $assignee->hasRole($stage->role()->value)) {
                throw ValidationException::withMessages([
                    'hierarchy' => 'The station approval hierarchy is incomplete. Ask an administrator to configure every reporting relationship.',
                ]);
            }

            $assignments[$stage->value] = $assignee;
            $assignee = $stage->next() ? $this->hierarchies->findSupervisorFor($assignee) : null;
        }

        return $assignments;
    }

    public function syncStationManager(User $user, array $stationIds): void
    {
        $this->hierarchies->syncStationManager($user, $stationIds);
    }

    public function ensureUserCanBeChanged(User $user): void
    {
        if ($this->hierarchies->userHasPendingApproval($user)) {
            throw ValidationException::withMessages(['user' => 'This user has a pending SDM approval and cannot be disabled or assigned another role.']);
        }
    }

    /** @return array<string, UserRole> */
    private function supervisorRoles(): array
    {
        return [
            UserRole::StationManager->value => UserRole::AssistantManager,
            UserRole::AssistantManager->value => UserRole::ManagerOperations,
            UserRole::ManagerOperations->value => UserRole::DeputyGeneralManager,
        ];
    }
}
