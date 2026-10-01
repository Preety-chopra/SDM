<?php

namespace App\Services;

use App\Contracts\Repositories\WorkflowRoleDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class WorkflowRoleDashboardService
{
    public function __construct(private WorkflowRoleDashboardRepositoryInterface $dashboard) {}

    /** @return array<string, mixed> */
    public function for(User $user, UserRole $role, CarbonInterface $dateFrom, CarbonInterface $dateTo, ?SdmType $serviceType = null, ?MemoStatus $status = null): array
    {
        $summary = $this->dashboard->summary($user, $role, $dateFrom, $dateTo, $serviceType, $status);
        $serviceRows = $this->dashboard->serviceCounts($user, $role, $dateFrom, $dateTo, $serviceType, $status);
        $statusRows = $this->dashboard->statusCounts($user, $role, $dateFrom, $dateTo, $serviceType, $status);
        $trendTo = CarbonImmutable::now()->endOfMonth();
        $trendFrom = $trendTo->startOfMonth()->subMonths(5);
        $trendRows = $this->dashboard->monthlyTrend($user, $role, $trendFrom, $trendTo, $serviceType, $status);
        $serviceCounts = $serviceRows->mapWithKeys(fn (object $row): array => [$row->service_type => (int) $row->aggregate]);

        return [
            ...$this->presentation($role),
            'total_received' => $summary['total'],
            'pending_count' => $summary['pending'],
            'completed_count' => $summary['completed'],
            'exception_count' => $summary['exceptions'],
            'service_distribution' => [
                'labels' => collect(SdmType::cases())->map(fn (SdmType $service): string => $service->code())->all(),
                'counts' => collect(SdmType::cases())->map(fn (SdmType $service): int => $serviceCounts[$service->value] ?? 0)->all(),
            ],
            'status_distribution' => [
                'labels' => $statusRows->map(fn (object $row): string => MemoStatus::from($row->status)->label())->all(),
                'statuses' => $statusRows->pluck('status')->all(),
                'counts' => $statusRows->map(fn (object $row): int => (int) $row->aggregate)->all(),
            ],
            'monthly_trend' => $this->monthlyTrend($trendRows, $trendFrom, $serviceType),
            'recent_sdms' => $this->dashboard->recentMemos($user, $role, $dateFrom, $dateTo, $serviceType, $status),
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'service_type' => $serviceType?->value ?? '',
                'status' => $status?->value ?? '',
            ],
            'services' => SdmType::cases(),
            'statuses' => collect(MemoStatus::cases())
                ->reject(fn (MemoStatus $status): bool => $status === MemoStatus::Draft)
                ->values()
                ->all(),
        ];
    }

    /** @return Collection<int, ServiceDeficiencyMemo> */
    public function memosForBar(
        User $user,
        UserRole $role,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        ?SdmType $serviceType,
        ?MemoStatus $status,
        int $limit,
    ): Collection {
        return $this->dashboard->recentMemos(
            $user,
            $role,
            $dateFrom,
            $dateTo,
            $serviceType,
            $status,
            $limit,
        );
    }

    /** @return array{title: string, eyebrow: string, description: string, completed_label: string} */
    private function presentation(UserRole $role): array
    {
        return match ($role) {
            UserRole::ManagerOperations => [
                'title' => 'AM/CMC Dashboard',
                'eyebrow' => 'My CMC review portfolio',
                'description' => 'Track SDMs routed to you for scrutiny coordination and onward submission.',
                'completed_label' => 'Forwarded onward',
            ],
            UserRole::CmcTeamLead => [
                'title' => 'CMC SM Dashboard',
                'eyebrow' => 'My CMC assignment portfolio',
                'description' => 'Track SDMs routed to you for assignment to CMC staff.',
                'completed_label' => 'Assignments completed',
            ],
            UserRole::CmcStaff => [
                'title' => 'CMC Staff Dashboard',
                'eyebrow' => 'My assigned CMC portfolio',
                'description' => 'Track your assigned scrutiny work and the subsequent penalty lifecycle.',
                'completed_label' => 'Assessment completed',
            ],
            UserRole::DeputyGeneralManager => [
                'title' => 'DGM/CMC Dashboard',
                'eyebrow' => 'My approval portfolio',
                'description' => 'Track SDMs routed to you for final CMC approval.',
                'completed_label' => 'Decisions completed',
            ],
            default => throw new \InvalidArgumentException('Unsupported workflow dashboard role.'),
        };
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{labels: list<string>, datasets: list<array{label: string, data: list<int>}>}
     */
    private function monthlyTrend(Collection $rows, CarbonInterface $from, ?SdmType $selectedService): array
    {
        $months = collect(range(0, 5))->map(fn (int $offset): CarbonInterface => $from->copy()->addMonths($offset));
        $counts = $rows->mapWithKeys(fn (object $row): array => [$row->period.'|'.$row->service_type => (int) $row->aggregate]);
        $services = $selectedService
            ? collect([$selectedService])
            : collect(SdmType::cases())->filter(fn (SdmType $service): bool => $rows->contains('service_type', $service->value))->values();

        return [
            'labels' => $months->map(fn (CarbonInterface $month): string => $month->format('M'))->all(),
            'datasets' => $services->map(fn (SdmType $service): array => [
                'label' => $service->code(),
                'data' => $months->map(fn (CarbonInterface $month): int => $counts[$month->format('Y-m').'|'.$service->value] ?? 0)->all(),
            ])->all(),
        ];
    }
}
