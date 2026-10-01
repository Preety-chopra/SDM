<?php

namespace App\Services;

use App\Contracts\Repositories\StationManagerDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class StationManagerDashboardService
{
    public function __construct(private StationManagerDashboardRepositoryInterface $dashboard) {}

    /** @return array<string, mixed> */
    public function for(User $manager, CarbonInterface $dateFrom, CarbonInterface $dateTo, ?SdmType $serviceType = null, ?MemoStatus $status = null): array
    {
        $summary = $this->dashboard->summary($manager, $dateFrom, $dateTo, $serviceType, $status);
        $stationRows = $this->dashboard->stationCounts($manager, $dateFrom, $dateTo, $serviceType, $status);
        $serviceRows = $this->dashboard->serviceCounts($manager, $dateFrom, $dateTo, $serviceType, $status);
        $trendTo = CarbonImmutable::now()->endOfMonth();
        $trendFrom = $trendTo->startOfMonth()->subMonths(5);
        $trendRows = $this->dashboard->monthlyTrend($manager, $trendFrom, $trendTo, $serviceType, $status);
        $serviceCounts = $serviceRows->mapWithKeys(fn (object $row): array => [$row->service_type => (int) $row->aggregate]);

        return [
            'total_submitted' => $summary['total'],
            'generated_count' => $summary['generated'],
            'pending_count' => $summary['pending'],
            'forwarded_count' => $summary['forwarded'],
            'cancelled_count' => $summary['cancelled'],
            'station_distribution' => [
                'labels' => $stationRows->pluck('station_name')->all(),
                'counts' => $stationRows->map(fn (object $row): int => (int) $row->aggregate)->all(),
            ],
            'service_distribution' => [
                'labels' => collect(SdmType::cases())->map(fn (SdmType $service): string => $service->code())->all(),
                'counts' => collect(SdmType::cases())->map(fn (SdmType $service): int => $serviceCounts[$service->value] ?? 0)->all(),
            ],
            'monthly_trend' => $this->monthlyTrend($trendRows, $trendFrom, $serviceType),
            'recent_sdms' => $this->dashboard->recentMemos($manager, $dateFrom, $dateTo, $serviceType, $status),
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'service_type' => $serviceType?->value ?? '',
                'status' => $status?->value ?? '',
            ],
            'services' => SdmType::cases(),
            'statuses' => MemoStatus::cases(),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{labels: list<string>, datasets: list<array{label: string, service_type: string, data: list<int>}>}
     */
    private function monthlyTrend(Collection $rows, CarbonInterface $from, ?SdmType $selectedService): array
    {
        $months = collect(range(0, 5))->map(fn (int $offset): CarbonInterface => $from->copy()->addMonths($offset));
        $counts = $rows->mapWithKeys(fn (object $row): array => [
            $row->period.'|'.$row->service_type => (int) $row->aggregate,
        ]);
        $services = $selectedService
            ? collect([$selectedService])
            : collect(SdmType::cases())->filter(
                fn (SdmType $service): bool => $rows->contains('service_type', $service->value),
            )->values();

        return [
            'labels' => $months->map(fn (CarbonInterface $month): string => $month->format('M'))->all(),
            'datasets' => $services->map(fn (SdmType $service): array => [
                'label' => $service->code(),
                'service_type' => $service->value,
                'data' => $months->map(
                    fn (CarbonInterface $month): int => $counts[$month->format('Y-m').'|'.$service->value] ?? 0,
                )->all(),
            ])->all(),
        ];
    }
}
