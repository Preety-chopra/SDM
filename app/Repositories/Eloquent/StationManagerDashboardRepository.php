<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StationManagerDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StationManagerDashboardRepository implements StationManagerDashboardRepositoryInterface
{
    public function summary(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): array
    {
        $query = $this->filteredQuery($manager, $from, $to, $serviceType, $status);

        return [
            'total' => (clone $query)->count(),
            'generated' => $this->memoQuery($from, $to, $serviceType, $status)
                ->where('created_by', $manager->id)
                ->count(),
            'pending' => (clone $query)->where(fn (Builder $query) => $this->whereManagerDecision($query, $manager, ['pending']))->count(),
            'forwarded' => (clone $query)->where(fn (Builder $query) => $this->whereManagerDecision($query, $manager, ['forwarded', 'approved']))->count(),
            'cancelled' => (clone $query)->where(fn (Builder $query) => $this->whereManagerDecision($query, $manager, ['cancelled', 'rejected']))->count(),
        ];
    }

    public function stationCounts(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection
    {
        return $this->filteredQuery($manager, $from, $to, $serviceType, $status)
            ->selectRaw("station_id, COALESCE(NULLIF(station_name, ''), 'Station not recorded') as station_name, count(*) as aggregate")
            ->groupBy('station_id', 'station_name')
            ->orderByDesc('aggregate')
            ->orderBy('station_name')
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
            ->where(fn (Builder $query) => $this->whereManagerDecision($query, $manager, ['forwarded', 'approved']))
            ->selectRaw("{$periodExpression} as period, service_type, count(*) as aggregate")
            ->groupBy('period', 'service_type')
            ->orderBy('period')
            ->toBase()
            ->get();
    }

    public function recentMemos(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status, int $limit = 10): Collection
    {
        return $this->filteredQuery($manager, $from, $to, $serviceType, $status)
            ->select(['id', 'service_type', 'station_name', 'status', 'submitted_at', 'created_at'])
            ->latest('submitted_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function filteredQuery(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Builder
    {
        return $this->memoQuery($from, $to, $serviceType, $status)
            ->where(function (Builder $query) use ($manager): void {
                $query->whereHas('workflowSteps', fn (Builder $query) => $query
                    ->where('stage', WorkflowStepType::StationManager->value)
                    ->where('assigned_to', $manager->id))
                    ->orWhereHas('approvals', fn (Builder $query) => $query
                        ->where('role', WorkflowStage::StationManager->value)
                        ->where('assigned_to', $manager->id));
            });
    }

    private function memoQuery(CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Builder
    {
        return ServiceDeficiencyMemo::query()
            ->whereBetween('submitted_at', [$from, $to])
            ->when($serviceType, fn (Builder $query) => $query->where('service_type', $serviceType->value))
            ->when($status, fn (Builder $query) => $query->where('status', $status->value));
    }

    /** @param list<string> $decisions */
    private function whereManagerDecision(Builder $query, User $manager, array $decisions): void
    {
        $query->whereHas('workflowSteps', fn (Builder $query) => $query
            ->where('stage', WorkflowStepType::StationManager->value)
            ->where('assigned_to', $manager->id)
            ->whereIn('decision', $decisions))
            ->orWhereHas('approvals', fn (Builder $query) => $query
                ->where('role', WorkflowStage::StationManager->value)
                ->where('assigned_to', $manager->id)
                ->whereIn('decision', $decisions));
    }
}
