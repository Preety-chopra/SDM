<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StationHierarchyRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\UserRole;
use App\Models\MemoApproval;
use App\Models\MemoWorkflowStep;
use App\Models\ReportingRelationship;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class StationHierarchyRepository implements StationHierarchyRepositoryInterface
{
    public function enabledUsersForRole(UserRole $role): Collection
    {
        return User::query()
            ->role($role->value)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_number']);
    }

    public function findEnabledUserForRoleByEmployeeNumber(UserRole $role, string $employeeNumber): ?User
    {
        return User::query()
            ->role($role->value)
            ->where('employee_number', $employeeNumber)
            ->where('is_enabled', true)
            ->first();
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
