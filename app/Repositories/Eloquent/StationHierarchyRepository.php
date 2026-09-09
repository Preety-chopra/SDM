<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StationHierarchyRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\UserRole;
use App\Models\MemoApproval;
use App\Models\MemoWorkflowStep;
use App\Models\ReportingRelationship;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\StationHierarchy;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class StationHierarchyRepository implements StationHierarchyRepositoryInterface
{
    public function allWithRelations(): Collection
    {
        return StationHierarchy::query()
            ->with(['station.metroLine', 'stationManager'])
            ->get()
            ->keyBy('station_id')
            ->pipe(function (Collection $hierarchies): Collection {
                return Station::query()->with('metroLine')->orderBy('name')->get()->map(function (Station $station) use ($hierarchies): StationHierarchy {
                    return $hierarchies->get($station->id) ?? new StationHierarchy(['station_id' => $station->id])
                        ->setRelation('station', $station)
                        ->setRelation('stationManager', null);
                });
            });
    }

    public function findForStation(int $stationId): ?StationHierarchy
    {
        return StationHierarchy::query()
            ->with(['stationManager.roles'])
            ->where('station_id', $stationId)
            ->first();
    }

    public function enabledUsersForRole(UserRole $role): Collection
    {
        return User::query()
            ->role($role->value)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_number']);
    }

    public function reportingRelationships(): Collection
    {
        return ReportingRelationship::query()
            ->with('supervisor:id,name,employee_number')
            ->get()
            ->keyBy('subordinate_id');
    }

    public function findEnabledUser(int $userId): ?User
    {
        return User::query()->with('roles')->whereKey($userId)->where('is_enabled', true)->first();
    }

    public function updateReportingRelationship(User $subordinate, User $supervisor): void
    {
        ReportingRelationship::query()->updateOrCreate(
            ['subordinate_id' => $subordinate->id],
            ['supervisor_id' => $supervisor->id],
        );
    }

    public function findSupervisorFor(User $subordinate): ?User
    {
        return ReportingRelationship::query()
            ->with('supervisor.roles')
            ->whereBelongsTo($subordinate, 'subordinate')
            ->first()
            ?->supervisor;
    }

    public function syncStationManager(User $user, array $stationIds): void
    {
        StationHierarchy::query()->where('station_manager_id', $user->id)->whereNotIn('station_id', $stationIds)->update(['station_manager_id' => null]);

        foreach ($stationIds as $stationId) {
            StationHierarchy::query()->updateOrCreate(
                ['station_id' => $stationId],
                ['station_manager_id' => $user->id],
            );
        }
    }

    public function userHasPendingApproval(User $user): bool
    {
        return MemoApproval::query()->whereBelongsTo($user, 'assignee')->where('decision', 'pending')
            ->whereHas('memo', fn ($query) => $query->whereIn('status', ['submitted', 'under_review']))
            ->exists()
            || MemoWorkflowStep::query()->whereBelongsTo($user, 'assignee')->where('decision', 'pending')
                ->whereHas('memo', fn ($query) => $query->whereColumn('memo_workflow_steps.id', 'service_deficiency_memos.current_workflow_step_id'))
                ->exists()
            || ServiceDeficiencyMemo::query()
                ->whereBelongsTo($user, 'cmcStaff')
                ->whereNotIn('status', [MemoStatus::Closed->value, MemoStatus::Cancelled->value])
                ->exists();
    }
}
