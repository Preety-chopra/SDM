<?php

namespace App\Contracts\Repositories;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface WorkflowRoleDashboardRepositoryInterface
{
    /** @return array{total: int, pending: int, completed: int, exceptions: int} */
    public function summary(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): array;

    /** @return Collection<int, object{service_type: string, aggregate: int|string}> */
    public function serviceCounts(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection;

    /** @return Collection<int, object{status: string, aggregate: int|string}> */
    public function statusCounts(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection;

    /** @return Collection<int, object{period: string, service_type: string, aggregate: int|string}> */
    public function monthlyTrend(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection;

    /** @return Collection<int, ServiceDeficiencyMemo> */
    public function recentMemos(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status, int $limit = 10): Collection;
}
