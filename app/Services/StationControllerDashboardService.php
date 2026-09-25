<?php

namespace App\Services;

use App\Contracts\Repositories\StationControllerDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class StationControllerDashboardService
{
    private const KPI_STATUSES = [
        MemoStatus::Draft,
        MemoStatus::AwaitingStationManager,
        MemoStatus::LineReview,
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
        $statusCounts = $this->visibleStatusCounts($summary['statuses']);
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
            $this->workflowStatuses(),
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
            'recent_sdms' => $this->withVisibleStatusLabels($this->dashboard->recentMemos(
                $creator,
                $dateFrom,
                $dateTo,
                $serviceType,
                $status,
                $aging,
                $pendingStatuses,
                $asOf,
            )),
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'service_type' => $serviceType?->value ?? '',
                'status' => $status?->value ?? '',
                'aging' => $aging ?? '',
            ],
            'services' => SdmType::cases(),
            'statuses' => collect(self::KPI_STATUSES)->map(fn (MemoStatus $status): array => [
                'value' => $status->value,
                'label' => $this->visibleStatusLabel($status),
            ])->all(),
        ];
    }

    /** @return Collection<int, ServiceDeficiencyMemo> */
    public function memosForBar(
        User $creator,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        ?SdmType $serviceType,
        ?MemoStatus $status,
        ?string $aging,
        int $limit,
    ): Collection {
        return $this->withVisibleStatusLabels($this->dashboard->recentMemos(
            $creator,
            $dateFrom,
            $dateTo,
            $serviceType,
            $status,
            $aging,
            $this->pendingStatuses(),
            CarbonImmutable::now(),
            $limit,
        ));
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
            'label' => $this->visibleStatusLabel($status),
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
        $rows = $rows
            ->groupBy(fn (object $row): string => $row->service_type.'|'.$this->visibleStatus($row->status)->value)
            ->map(function (Collection $group): object {
                $first = $group->first();

                return (object) [
                    'service_type' => $first->service_type,
                    'status' => $this->visibleStatus($first->status)->value,
                    'aggregate' => $group->sum(fn (object $row): int => (int) $row->aggregate),
                ];
            })
            ->values();
        $services = $selectedService
            ? collect([$selectedService])
            : collect(SdmType::cases())->filter(
                fn (SdmType $service): bool => $rows->contains('service_type', $service->value),
            )->values();
        $statuses = $selectedStatus && in_array($selectedStatus, self::KPI_STATUSES, true) && $selectedStatus !== MemoStatus::Draft
            ? collect([$selectedStatus])
            : collect([MemoStatus::AwaitingStationManager, MemoStatus::LineReview])->filter(
                fn (MemoStatus $status): bool => $rows->contains('status', $status->value),
            )->values();
        $counts = $rows->mapWithKeys(fn (object $row): array => [
            $row->service_type.'|'.$row->status => (int) $row->aggregate,
        ]);

        return [
            'labels' => $services->map(fn (SdmType $service): string => $service->code())->all(),
            'service_types' => $services->map(fn (SdmType $service): string => $service->value)->all(),
            'datasets' => $statuses->map(fn (MemoStatus $status): array => [
                'label' => $this->visibleStatusLabel($status),
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
        return [MemoStatus::Submitted->value, MemoStatus::AwaitingStationManager->value];
    }

    /** @return list<string> */
    private function workflowStatuses(): array
    {
        return collect(MemoStatus::cases())
            ->reject(fn (MemoStatus $status): bool => $status === MemoStatus::Draft)
            ->map(fn (MemoStatus $status): string => $status->value)
            ->all();
    }

    /** @param array<string, int> $counts
     * @return array<string, int>
     */
    private function visibleStatusCounts(array $counts): array
    {
        $visibleCounts = $this->withZeroCounts(MemoStatus::cases(), []);

        foreach ($counts as $status => $count) {
            $visibleCounts[$this->visibleStatus($status)->value] += $count;
        }

        return $visibleCounts;
    }

    public function visibleStatusLabel(MemoStatus $status): string
    {
        return match ($this->visibleStatus($status)) {
            MemoStatus::Draft => 'Draft',
            MemoStatus::AwaitingStationManager => 'Pending with SM',
            MemoStatus::LineReview => 'Processed by SM',
            default => 'Processed by SM',
        };
    }

    private function visibleStatus(MemoStatus|string $status): MemoStatus
    {
        $status = is_string($status) ? MemoStatus::from($status) : $status;

        return match ($status) {
            MemoStatus::Draft => MemoStatus::Draft,
            MemoStatus::Submitted, MemoStatus::AwaitingStationManager => MemoStatus::AwaitingStationManager,
            default => MemoStatus::LineReview,
        };
    }

    /** @param Collection<int, ServiceDeficiencyMemo> $memos
     * @return Collection<int, ServiceDeficiencyMemo>
     */
    private function withVisibleStatusLabels(Collection $memos): Collection
    {
        return $memos->each(function (ServiceDeficiencyMemo $memo): void {
            $memo->setAttribute('dashboard_status', $this->visibleStatus($memo->status)->value);
            $memo->setAttribute('dashboard_status_label', $this->visibleStatusLabel($memo->status));
        });
    }

    private function percentage(int $count, int $total): float
    {
        return round(($count / max(1, $total)) * 100, 1);
    }
}
