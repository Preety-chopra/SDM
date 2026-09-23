<?php

namespace App\Contracts\Repositories;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface StationManagerDashboardRepositoryInterface
{
    /** @return array{total: int, pending: int, forwarded: int, cancelled: int} */
    public function summary(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): array;

    /** @return Collection<int, object{station_id: int|null, station_name: string, aggregate: int|string}> */
    public function stationCounts(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection;

    /** @return Collection<int, object{service_type: string, aggregate: int|string}> */
    public function serviceCounts(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection;

    /** @return Collection<int, object{period: string, service_type: string, aggregate: int|string}> */
    public function monthlyTrend(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection;

    /** @return Collection<int, ServiceDeficiencyMemo> */
    public function recentMemos(User $manager, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status, int $limit = 10): Collection;
}
