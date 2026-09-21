<?php

namespace App\Contracts\Repositories;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface StationControllerDashboardRepositoryInterface
{
    /**
     * @return array{statuses: array<string, int>, services: array<string, int>}
     */
    public function summary(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
    ): array;

    /** @return Collection<int, object{period: string, service_type: string, aggregate: int|string}> */
    public function monthlyTrend(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
    ): Collection;

    /** @param list<string> $pendingStatuses
     * @return Collection<int, object{service_type: string, status: string, aggregate: int|string}>
     */
    public function workflowByService(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
        array $pendingStatuses,
    ): Collection;

    /** @param list<string> $pendingStatuses
     * @return array<string, int>
     */
    public function agingCounts(
        User $creator,
        CarbonInterface $from,
        CarbonInterface $to,
        ?SdmType $serviceType,
        ?MemoStatus $status,
        array $pendingStatuses,
        CarbonInterface $asOf,
    ): array;

    /** @param list<string> $pendingStatuses
     * @return Collection<int, ServiceDeficiencyMemo>
     */
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
    ): Collection;
}
