<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserActivityReportRepositoryInterface;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\MemoApproval;
use App\Models\MemoWorkflowStep;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UserActivityReportRepository implements UserActivityReportRepositoryInterface
{
    public function summary(array $filters): array
    {
        $created = $this->filtered(ServiceDeficiencyMemo::withTrashed(), $filters, 'created_by', 'created_at')
            ->selectRaw("id as memo_id, created_by as actor_id, 'created' as stage, 'created' as outcome")
            ->toBase();

        $steps = $this->filtered(MemoWorkflowStep::query(), $filters, 'acted_by', 'acted_at')
            ->whereHas('memo', fn (Builder $query) => $query->withTrashed()->where('workflow_version', '>=', 2))
            ->whereNotNull('acted_at')->whereIn('decision', ['forwarded', 'approved'])
            ->selectRaw('memo_id, acted_by as actor_id, stage, decision as outcome')->toBase();

        // Legacy approvals used "approved" for both onward forwarding and final DGM approval.
        $legacy = $this->filtered(MemoApproval::query(), $filters, 'acted_by', 'acted_at')
            ->whereHas('memo', fn (Builder $query) => $query->withTrashed()->where('workflow_version', '<', 2))
            ->whereNotNull('acted_at')->where('decision', 'approved')
            ->selectRaw('memo_id, acted_by as actor_id')
            ->selectRaw('case role when ? then ? when ? then ? when ? then ? else ? end as stage', [
                WorkflowStage::StationManager->value, WorkflowStepType::StationManager->value,
                WorkflowStage::LineManager->value, WorkflowStepType::LineReview->value,
                WorkflowStage::DeputyGeneralManager->value, WorkflowStepType::DgmApproval->value,
                'legacy_manager_operations',
            ])
            ->selectRaw('case when role = ? then ? else ? end as outcome', [WorkflowStage::DeputyGeneralManager->value, 'approved', 'forwarded'])
            ->toBase();

        $records = DB::query()->fromSub($created->unionAll($steps)->unionAll($legacy), 'contributions');

        return [
            'totals' => (clone $records)->selectRaw('outcome, count(distinct memo_id) as memos, count(*) as actions')
                ->groupBy('outcome')->get(),
            'levels' => (clone $records)->where('outcome', '!=', 'created')
                ->selectRaw('stage, outcome, count(distinct memo_id) as memos, count(*) as actions')
                ->groupBy('stage', 'outcome')->get(),
            'people' => (clone $records)->selectRaw('actor_id, outcome, count(distinct memo_id) as memos, count(*) as actions')
                ->groupBy('actor_id', 'outcome')->orderBy('actor_id')->get(),
        ];
    }

    private function filtered(Builder $query, array $filters, string $actorColumn, string $dateColumn): Builder
    {
        return $query
            ->when($filters['actor_id'] ?? null, fn (Builder $query, $id) => $query->where($actorColumn, $id))
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->where($dateColumn, '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->where($dateColumn, '<', Carbon::parse($date)->addDay()->startOfDay()));
    }
}
