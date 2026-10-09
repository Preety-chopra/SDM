<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\LineManagerDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LineManagerDashboardRepository implements LineManagerDashboardRepositoryInterface
{
    public function summary(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): array
    {
        $query = $this->filteredQuery($manager, $from, $to, $serviceType, $status);

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where(fn (Builder $query) => $this->whereManagerDecision($query, $manager, ['pending']))->count(),
            'forwarded' => (clone $query)->where(fn (Builder $query) => $this->whereManagerDecision($query, $manager, ['forwarded', 'approved']))->count(),
            'cancelled' => (clone $query)->where(fn (Builder $query) => $this->whereManagerDecision($query, $manager, ['cancelled', 'rejected']))->count(),
        ];
    }

    public function lineCounts(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection
    {
        $memoTable = (new ServiceDeficiencyMemo)->getTable();
        $lineTable = (new Line)->getTable();

        return $this->filteredQuery($manager, $from, $to, $serviceType, $status)
            ->leftJoin("{$lineTable} as dashboard_lines", 'dashboard_lines.code', '=', "{$memoTable}.line_code")
            ->selectRaw("{$memoTable}.line_code, COALESCE(dashboard_lines.color, '#A4A4A4') as color, count(*) as aggregate")
            ->groupBy("{$memoTable}.line_code", 'dashboard_lines.color')
            ->orderByDesc('aggregate')
            ->orderBy("{$memoTable}.line_code")
            ->toBase()
            ->get();
    }

    public function serviceCounts(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection
    {
        return $this->filteredQuery($manager, $from, $to, $serviceType, $status)
            ->selectRaw('service_type, count(*) as aggregate')
            ->groupBy('service_type')
            ->toBase()
            ->get();
    }

    public function monthlyTrend(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection
    {
        $periodExpression = ServiceDeficiencyMemo::query()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', submitted_at)"
            : "DATE_FORMAT(submitted_at, '%Y-%m')";

        return $this->filteredQuery($manager, $from, $to, $serviceType, $status)
            ->selectRaw("{$periodExpression} as period, service_type, count(*) as aggregate")
            ->groupBy('period', 'service_type')
            ->orderBy('period')
            ->toBase()
            ->get();
    }

    public function recentMemos(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status, int $limit = 10): Collection
    {
        return $this->filteredQuery($manager, $from, $to, $serviceType, $status)
            ->select(['id', 'service_type', 'station_name', 'line_code', 'line_name', 'status', 'submitted_at', 'created_at'])
            ->latest('submitted_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function pendingMemos(User $manager): Collection
    {
        return ServiceDeficiencyMemo::query()
            ->select(['id', 'service_type', 'serial_number', 'is_urgent', 'station_name', 'submitted_at'])
            ->where(function (Builder $query) use ($manager): void {
                $query->whereHas('currentWorkflowStep', fn (Builder $query) => $query
                    ->where('stage', WorkflowStepType::LineReview->value)
                    ->where('assigned_to', $manager->id)
                    ->where('decision', 'pending'))
                    ->orWhere(function (Builder $query) use ($manager): void {
                        $query->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                            ->whereHas('approvals', fn (Builder $query) => $query
                                ->where('role', WorkflowStage::LineManager->value)
                                ->where('assigned_to', $manager->id)
                                ->where('decision', 'pending')
                                ->whereColumn('memo_approvals.role', 'service_deficiency_memos.current_stage'));
                    });
            })
            ->latest('submitted_at')
            ->latest('id')
            ->get();
    }

    public function memosForLine(User $manager, string $lineCode, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status, int $limit): Collection
    {
        return $this->filteredQuery($manager, $from, $to, $serviceType, $status)
            ->where('line_code', $lineCode)
            ->select(['id', 'service_type', 'station_name', 'line_code', 'status', 'submitted_at'])
            ->latest('submitted_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function filteredQuery(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Builder
    {
        return ServiceDeficiencyMemo::query()
            ->whereBetween('submitted_at', [$from, $to])
            ->where(function (Builder $query) use ($manager): void {
                $query->whereHas('workflowSteps', fn (Builder $query) => $query
                    ->where('stage', WorkflowStepType::LineReview->value)
                    ->where('assigned_to', $manager->id))
                    ->orWhereHas('approvals', fn (Builder $query) => $query
                        ->where('role', WorkflowStage::LineManager->value)
                        ->where('assigned_to', $manager->id));
            })
            ->when($serviceType, fn (Builder $query) => $query->where('service_type', $serviceType->value))
            ->when($status, fn (Builder $query) => $query->where('status', $status->value));
    }

    /** @param list<string> $decisions */
    private function whereManagerDecision(Builder $query, User $manager, array $decisions): void
    {
        $query->whereHas('workflowSteps', fn (Builder $query) => $query
            ->where('stage', WorkflowStepType::LineReview->value)
            ->where('assigned_to', $manager->id)
            ->whereIn('decision', $decisions))
            ->orWhereHas('approvals', fn (Builder $query) => $query
                ->where('role', WorkflowStage::LineManager->value)
                ->where('assigned_to', $manager->id)
                ->whereIn('decision', $decisions));
    }
}
