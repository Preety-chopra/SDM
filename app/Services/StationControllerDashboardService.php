<?php

namespace App\Services;

use App\Contracts\Repositories\StationControllerDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class StationControllerDashboardService
{
    private const KPI_STATUSES = [
        MemoStatus::AwaitingStationManager,
        MemoStatus::LineReview,
        MemoStatus::AmCmcReview,
    ];

    public function __construct(
        private StationControllerDashboardRepositoryInterface $dashboard,
    ) {}

    /** @return array<string, mixed> */
    public function for(
        User $creator,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        ?SdmType $serviceType = null,
        ?MemoStatus $status = null,
        ?string $aging = null,
    ): array {
        $summary = $this->dashboard->summary($creator, $dateFrom, $dateTo, $serviceType, $status);
        $statusCounts = $this->withZeroCounts(MemoStatus::cases(), $summary['statuses']);
        $serviceCounts = $this->withZeroCounts(SdmType::cases(), $summary['services']);
        $total = array_sum($statusCounts);
        $pendingStatuses = $this->pendingStatuses();
        $asOf = CarbonImmutable::now();
        $trendTo = $asOf->endOfMonth();
        $trendFrom = $asOf->startOfMonth()->subMonths(5);
        $trendRows = $this->dashboard->monthlyTrend($creator, $trendFrom, $trendTo, $serviceType, $status);
        $workflowRows = $this->dashboard->workflowByService(
            $creator,
            $dateFrom,
            $dateTo,
            $serviceType,
            $status,
            $pendingStatuses,
        );
        $agingCounts = $this->dashboard->agingCounts(
            $creator,
            $dateFrom,
            $dateTo,
            $serviceType,
            $status,
            $pendingStatuses,
            $asOf,
        );

        return [
            'total_sdms' => $total,
            'status_counts' => $statusCounts,
            'service_counts' => $serviceCounts,
            'kpis' => $this->kpis($statusCounts, $total),
            'monthly_trend' => $this->monthlyTrend($trendRows, $trendFrom, $serviceType),
            'service_distribution' => $this->serviceDistribution($serviceCounts, $total),
            'workflow_by_service' => $this->workflowByService($workflowRows, $serviceType, $status),
            'aging_buckets' => $this->agingBuckets($agingCounts),
            'recent_sdms' => $this->dashboard->recentMemos(
                $creator,
                $dateFrom,
                $dateTo,
                $serviceType,
                $status,
                $aging,
                $pendingStatuses,
                $asOf,
            ),
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'service_type' => $serviceType?->value ?? '',
                'status' => $status?->value ?? '',
                'aging' => $aging ?? '',
            ],
            'services' => SdmType::cases(),
            'statuses' => MemoStatus::cases(),
        ];
    }

    /**
     * @param  array<int, MemoStatus|SdmType>  $cases
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function withZeroCounts(array $cases, array $counts): array
    {
        $result = [];

        foreach ($cases as $case) {
            $result[$case->value] = $counts[$case->value] ?? 0;
        }

        return $result;
    }

    /** @param array<string, int> $counts
     * @return list<array{label: string, status: string, count: int, percentage: float}>
     */
    private function kpis(array $counts, int $total): array
    {
        return array_map(fn (MemoStatus $status): array => [
            'label' => $status->label(),
            'status' => $status->value,
            'count' => $counts[$status->value],
            'percentage' => $this->percentage($counts[$status->value], $total),
        ], self::KPI_STATUSES);
    }

    /** @param Collection<int, object> $rows
     * @return array{labels: list<string>, datasets: list<array{label: string, service_type: string, data: list<int>}>}
     */
    private function monthlyTrend(Collection $rows, CarbonInterface $from, ?SdmType $selectedService): array
    {
        $months = collect(range(0, 5))->map(fn (int $offset) => $from->copy()->addMonths($offset));
        $counts = $rows->mapWithKeys(fn (object $row): array => [
            $row->period.'|'.$row->service_type => (int) $row->aggregate,
        ]);
        $services = $selectedService
            ? collect([$selectedService])
            : collect(SdmType::cases())->filter(
                fn (SdmType $service): bool => $rows->contains('service_type', $service->value),
            )->values();

        $datasets = $services->map(fn (SdmType $service): array => [
            'label' => $service->code(),
            'service_type' => $service->value,
            'data' => $months->map(
                fn (CarbonInterface $month): int => $counts[$month->format('Y-m').'|'.$service->value] ?? 0,
            )->all(),
        ])->all();

        if ($datasets === []) {
            $datasets[] = [
                'label' => 'SDMs',
                'service_type' => '',
                'data' => array_fill(0, 6, 0),
            ];
        }

        return [
            'labels' => $months->map(fn (CarbonInterface $month): string => $month->format('M'))->all(),
            'datasets' => $datasets,
        ];
    }

    /** @param array<string, int> $counts
     * @return array{labels: list<string>, service_types: list<string>, counts: list<int>, percentages: list<float>}
     */
    private function serviceDistribution(array $counts, int $total): array
    {
        $services = collect(SdmType::cases());

        return [
            'labels' => $services->map(fn (SdmType $service): string => $service->code())->all(),
            'service_types' => $services->map(fn (SdmType $service): string => $service->value)->all(),
            'counts' => $services->map(fn (SdmType $service): int => $counts[$service->value])->all(),
            'percentages' => $services->map(
                fn (SdmType $service): float => $this->percentage($counts[$service->value], $total),
            )->all(),
        ];
    }

    /** @param Collection<int, object> $rows
     * @return array{labels: list<string>, service_types: list<string>, datasets: list<array{label: string, status: string, data: list<int>}>}
     */
    private function workflowByService(Collection $rows, ?SdmType $selectedService, ?MemoStatus $selectedStatus): array
    {
        $services = $selectedService
            ? collect([$selectedService])
            : collect(SdmType::cases())->filter(
                fn (SdmType $service): bool => $rows->contains('service_type', $service->value),
            )->values();
        $statuses = $selectedStatus && in_array($selectedStatus->value, $this->pendingStatuses(), true)
            ? collect([$selectedStatus])
            : collect(MemoStatus::cases())->filter(
                fn (MemoStatus $status): bool => $rows->contains('status', $status->value),
            )->values();
        $counts = $rows->mapWithKeys(fn (object $row): array => [
            $row->service_type.'|'.$row->status => (int) $row->aggregate,
        ]);

        return [
            'labels' => $services->map(fn (SdmType $service): string => $service->code())->all(),
            'service_types' => $services->map(fn (SdmType $service): string => $service->value)->all(),
            'datasets' => $statuses->map(fn (MemoStatus $status): array => [
                'label' => $status->label(),
                'status' => $status->value,
                'data' => $services->map(
                    fn (SdmType $service): int => $counts[$service->value.'|'.$status->value] ?? 0,
                )->all(),
            ])->all(),
        ];
    }

    /** @param array<string, int> $counts
     * @return array{labels: list<string>, values: list<string>, counts: list<int>}
     */
    private function agingBuckets(array $counts): array
    {
        return [
            'labels' => ['0-2 days', '3-7 days', '8-15 days', '>15 days'],
            'values' => ['0_2', '3_7', '8_15', 'over_15'],
            'counts' => [
                $counts['0_2'] ?? 0,
                $counts['3_7'] ?? 0,
                $counts['8_15'] ?? 0,
                $counts['over_15'] ?? 0,
            ],
        ];
    }

    /** @return list<string> */
    private function pendingStatuses(): array
    {
        return collect(MemoStatus::cases())
            ->reject(fn (MemoStatus $status): bool => in_array($status, [MemoStatus::Draft, MemoStatus::Closed, MemoStatus::Cancelled], true))
            ->map(fn (MemoStatus $status): string => $status->value)
            ->all();
    }

    private function percentage(int $count, int $total): float
    {
        return round(($count / max(1, $total)) * 100, 1);
    }
}
