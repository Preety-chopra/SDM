<?php

namespace App\Services;

use App\Contracts\Repositories\LineManagerDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class LineManagerDashboardService
{
    public function __construct(private LineManagerDashboardRepositoryInterface $dashboard) {}

    /** @return array<string, mixed> */
    public function for(User $manager, CarbonInterface $dateFrom, CarbonInterface $dateTo, ?SdmType $serviceType = null, ?MemoStatus $status = null): array
    {
        $summary = $this->dashboard->summary($manager, $dateFrom, $dateTo, $serviceType, $status);
        $lineRows = $this->dashboard->lineCounts($manager, $dateFrom, $dateTo, $serviceType, $status)
            ->sortBy(fn (object $row): string => $this->lineSortKey($row->line_code))
            ->values();
        $serviceRows = $this->dashboard->serviceCounts($manager, $dateFrom, $dateTo, $serviceType, $status);
        $trendTo = CarbonImmutable::now()->endOfMonth();
        $trendFrom = $trendTo->startOfMonth()->subMonths(5);
        $trendRows = $this->dashboard->monthlyTrend($manager, $trendFrom, $trendTo, $serviceType, $status);
        $serviceCounts = $serviceRows->mapWithKeys(fn (object $row): array => [$row->service_type => (int) $row->aggregate]);

        return [
            'total_received' => $summary['total'],
            'pending_count' => $summary['pending'],
            'forwarded_count' => $summary['forwarded'],
            'cancelled_count' => $summary['cancelled'],
            'line_distribution' => [
                'labels' => $lineRows->map(fn (object $row): string => $row->line_code ?: 'N/A')->all(),
                'counts' => $lineRows->map(fn (object $row): int => (int) $row->aggregate)->all(),
                'colors' => $lineRows->pluck('color')->all(),
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
            'statuses' => collect(MemoStatus::lineManagerFilterCases())->map(fn (MemoStatus $status): array => [
                'value' => $status->value,
                'label' => $status->lineManagerFilterLabel(),
            ])->all(),
        ];
    }

    /** @return Collection<int, ServiceDeficiencyMemo> */
    public function memosForLine(User $manager, string $lineCode, CarbonInterface $dateFrom, CarbonInterface $dateTo, ?SdmType $serviceType = null, ?MemoStatus $status = null, int $limit = 100): Collection
    {
        return $this->dashboard->memosForLine($manager, $lineCode, $dateFrom, $dateTo, $serviceType, $status, $limit);
    }

    /** @return Collection<int, ServiceDeficiencyMemo> */
    public function pendingMemos(User $manager): Collection
    {
        return $this->dashboard->pendingMemos($manager);
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

    private function lineSortKey(?string $lineCode): string
    {
        $lineCode = Str::upper(trim((string) $lineCode));

        if ($lineCode === 'AEL') {
            return '10|AEL';
        }

        if (preg_match('/(?:^|\D)([1-9])(?:\D|$)/', $lineCode, $matches) === 1) {
            return sprintf('%02d|%s', (int) $matches[1], $lineCode);
        }

        return '11|'.$lineCode;
    }
}
