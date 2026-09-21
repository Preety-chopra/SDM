<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StationControllerDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Models\MemoWorkflowStep;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StationControllerDashboardRepository implements StationControllerDashboardRepositoryInterface
{
    public function summary(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
    ): array {
        $query = $this->filteredQuery($creator, $from, $to, $serviceType, $status);

        return [
            'statuses' => (clone $query)
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn (mixed $count): int => (int) $count)
                ->all(),
            'services' => (clone $query)
                ->selectRaw('service_type, count(*) as aggregate')
                ->groupBy('service_type')
                ->pluck('aggregate', 'service_type')
                ->map(fn (mixed $count): int => (int) $count)
                ->all(),
        ];
    }

    public function monthlyTrend(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
    ): Collection {
        $periodExpression = ServiceDeficiencyMemo::query()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        return $this->filteredQuery($creator, $from, $to, $serviceType, $status)
            ->selectRaw("{$periodExpression} as period, service_type, count(*) as aggregate")
            ->groupBy('period', 'service_type')
            ->orderBy('period')
            ->toBase()
            ->get();
    }

    public function workflowByService(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
        array $pendingStatuses,
    ): Collection {
        return $this->filteredQuery($creator, $from, $to, $serviceType, $status)
            ->whereIn('status', $pendingStatuses)
            ->selectRaw('service_type, status, count(*) as aggregate')
            ->groupBy('service_type', 'status')
            ->toBase()
            ->get();
    }

    public function agingCounts(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
        array $pendingStatuses,
        CarbonInterface $asOf,
    ): array {
        $memoTable = (new ServiceDeficiencyMemo)->getTable();
        $stepTable = (new MemoWorkflowStep)->getTable();
        $pendingDate = "COALESCE(current_step.created_at, {$memoTable}.submitted_at, {$memoTable}.created_at)";
        $asOfDate = $asOf->toDateString();
        $age = $this->ageInDaysExpression($pendingDate);

        return $this->filteredQuery($creator, $from, $to, $serviceType, $status, true)
            ->leftJoin("{$stepTable} as current_step", 'current_step.id', '=', "{$memoTable}.current_workflow_step_id")
            ->whereIn("{$memoTable}.status", $pendingStatuses)
            ->selectRaw(
                "CASE
                    WHEN {$age} <= 2 THEN '0_2'
                    WHEN {$age} <= 7 THEN '3_7'
                    WHEN {$age} <= 15 THEN '8_15'
                    ELSE 'over_15'
                END as age_bucket, count(*) as aggregate",
                [$asOfDate, $asOfDate, $asOfDate],
            )
            ->groupBy('age_bucket')
            ->pluck('aggregate', 'age_bucket')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function recentMemos(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
        ?string $aging,
        array $pendingStatuses,
        CarbonInterface $asOf,
        int $limit = 10,
    ): Collection {
        $memoTable = (new ServiceDeficiencyMemo)->getTable();
        $stepTable = (new MemoWorkflowStep)->getTable();
        $query = $this->filteredQuery($creator, $from, $to, $serviceType, $status, true)
            ->select([
                "{$memoTable}.id",
                "{$memoTable}.service_type",
                "{$memoTable}.serial_number",
                "{$memoTable}.status",
                "{$memoTable}.created_at",
                "{$memoTable}.created_by",
            ]);

        if ($aging !== null) {
            $pendingDate = "COALESCE(current_step.created_at, {$memoTable}.submitted_at, {$memoTable}.created_at)";
            $age = $this->ageInDaysExpression($pendingDate);
            $query
                ->leftJoin("{$stepTable} as current_step", 'current_step.id', '=', "{$memoTable}.current_workflow_step_id")
                ->whereIn("{$memoTable}.status", $pendingStatuses);

            match ($aging) {
                '0_2' => $query->whereRaw("{$age} between 0 and 2", [$asOf->toDateString()]),
                '3_7' => $query->whereRaw("{$age} between 3 and 7", [$asOf->toDateString()]),
                '8_15' => $query->whereRaw("{$age} between 8 and 15", [$asOf->toDateString()]),
                'over_15' => $query->whereRaw("{$age} > 15", [$asOf->toDateString()]),
            };
        }

        return $query
            ->latest("{$memoTable}.created_at")
            ->latest("{$memoTable}.id")
            ->limit($limit)
            ->get();
    }

    private function filteredQuery(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
        bool $qualifyColumns = false,
    ): Builder {
        $prefix = $qualifyColumns ? (new ServiceDeficiencyMemo)->getTable().'.' : '';

        return ServiceDeficiencyMemo::query()
            ->where($prefix.'created_by', $creator->id)
            ->whereBetween($prefix.'created_at', [$from, $to])
            ->when($serviceType, fn (Builder $query) => $query->where($prefix.'service_type', $serviceType->value))
            ->when($status, fn (Builder $query) => $query->where($prefix.'status', $status->value));
    }

    private function ageInDaysExpression(string $dateExpression): string
    {
        return ServiceDeficiencyMemo::query()->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(julianday(?) - julianday(DATE({$dateExpression})) AS INTEGER)"
            : "DATEDIFF(?, DATE({$dateExpression}))";
    }
}
