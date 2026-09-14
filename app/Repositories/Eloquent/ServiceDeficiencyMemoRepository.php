<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ServiceDeficiencyMemoRepository implements ServiceDeficiencyMemoRepositoryInterface
{
    public function paginateFor(User $user, ?WorkflowStage $pendingStage = null): LengthAwarePaginator
    {
        return ServiceDeficiencyMemo::query()
            ->select(['id', 'service_type', 'serial_number', 'station_name', 'line_name', 'incident_date', 'status', 'workflow_version', 'current_stage', 'current_workflow_step_id', 'cmc_staff_id', 'created_by', 'submitted_at', 'created_at'])
            ->with([
                'currentWorkflowStep:id,stage,assigned_name',
                'cmcStaff:id,name',
                'cancellationApproval' => fn ($query) => $query->select(['memo_approvals.id', 'memo_approvals.memo_id', 'memo_approvals.role', 'memo_approvals.decision', 'memo_approvals.acted_at']),
                'cancellationWorkflowStep' => fn ($query) => $query->select(['memo_workflow_steps.id', 'memo_workflow_steps.memo_id', 'memo_workflow_steps.stage', 'memo_workflow_steps.decision', 'memo_workflow_steps.acted_at']),
            ])
            ->when($pendingStage, fn ($query) => $query
                ->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                ->where('current_stage', $pendingStage->value)
                ->whereHas('approvals', fn ($query) => $query
                    ->where('role', $pendingStage->value)
                    ->where('decision', 'pending')))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function pendingByStage(int $limit = 5): array
    {
        $pending = [];

        foreach (WorkflowStage::cases() as $stage) {
            $query = ServiceDeficiencyMemo::query()
                ->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                ->where('current_stage', $stage->value)
                ->whereHas('approvals', fn ($query) => $query
                    ->where('role', $stage->value)
                    ->where('decision', 'pending'));

            $pending[$stage->value] = [
                'memos' => (clone $query)
                    ->select(['id', 'serial_number', 'station_name', 'line_name', 'incident_date', 'current_stage', 'submitted_at'])
                    ->with(['approvals' => fn ($query) => $query
                        ->select(['id', 'memo_id', 'role', 'assigned_name'])
                        ->where('role', $stage->value)
                        ->where('decision', 'pending')])
                    ->latest('submitted_at')
                    ->latest('id')
                    ->limit($limit)
                    ->get(),
                'total' => (clone $query)->count(),
            ];
        }

        return $pending;
    }

    public function pendingActionCount(User $user): int
    {
        return ServiceDeficiencyMemo::query()
            ->where(function ($query) use ($user): void {
                $query->where(function ($query) use ($user): void {
                    $query->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                        ->whereHas('approvals', fn ($query) => $query
                            ->where('assigned_to', $user->id)
                            ->where('decision', 'pending')
                            ->whereColumn('memo_approvals.role', 'service_deficiency_memos.current_stage'));
                })->orWhereHas('currentWorkflowStep', fn ($query) => $query
                    ->where('assigned_to', $user->id)
                    ->where('decision', 'pending'));
            })
            ->count();
    }

    public function pendingByWorkflowStep(int $limit = 5): array
    {
        $pending = [];
        foreach (WorkflowStepType::cases() as $stage) {
            $query = ServiceDeficiencyMemo::query()->whereHas('currentWorkflowStep', fn ($query) => $query->where('stage', $stage->value)->where('decision', 'pending'));
            $pending[$stage->value] = [
                'memos' => (clone $query)->with('currentWorkflowStep:id,stage,assigned_name')->latest('submitted_at')->limit($limit)->get(),
                'total' => (clone $query)->count(),
            ];
        }

        return $pending;
    }

    public function create(array $attributes): ServiceDeficiencyMemo
    {
        return ServiceDeficiencyMemo::query()->create($attributes);
    }

    public function update(ServiceDeficiencyMemo $memo, array $attributes): ServiceDeficiencyMemo
    {
        $memo->update($attributes);

        return $memo->refresh();
    }

    public function forceDelete(ServiceDeficiencyMemo $memo): void
    {
        $memo->forceDelete();
    }
}
