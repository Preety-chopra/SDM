<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\WorkflowRoleDashboardRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class WorkflowRoleDashboardRepository implements WorkflowRoleDashboardRepositoryInterface
{
    public function summary(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): array
    {
        $query = $this->filteredQuery($user, $role, $from, $to, $serviceType, $status);

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where(fn (Builder $query) => $this->whereDecision($query, $user, $role, ['pending']))->count(),
            'completed' => (clone $query)->where(fn (Builder $query) => $this->whereDecision($query, $user, $role, ['forwarded', 'approved']))->count(),
            'exceptions' => (clone $query)->where(fn (Builder $query) => $this->whereDecision($query, $user, $role, ['returned', 'cancelled', 'rejected']))->count(),
        ];
    }

    public function serviceCounts(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection
    {
        return $this->filteredQuery($user, $role, $from, $to, $serviceType, $status)
            ->selectRaw('service_type, count(*) as aggregate')
            ->groupBy('service_type')
            ->toBase()
            ->get();
    }

    public function statusCounts(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection
    {
        return $this->filteredQuery($user, $role, $from, $to, $serviceType, $status)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->orderByDesc('aggregate')
            ->toBase()
            ->get();
    }

    public function monthlyTrend(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Collection
    {
        $periodExpression = ServiceDeficiencyMemo::query()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', submitted_at)"
            : "DATE_FORMAT(submitted_at, '%Y-%m')";

        return $this->filteredQuery($user, $role, $from, $to, $serviceType, $status)
            ->selectRaw("{$periodExpression} as period, service_type, count(*) as aggregate")
            ->groupBy('period', 'service_type')
            ->orderBy('period')
            ->toBase()
            ->get();
    }

    public function recentMemos(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status, int $limit = 10): Collection
    {
        return $this->filteredQuery($user, $role, $from, $to, $serviceType, $status)
            ->select(['id', 'service_type', 'station_name', 'line_code', 'status', 'submitted_at', 'created_at'])
            ->latest('submitted_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function filteredQuery(User $user, UserRole $role, CarbonInterface $from, CarbonInterface $to, ?SdmType $serviceType, ?MemoStatus $status): Builder
    {
        return ServiceDeficiencyMemo::query()
            ->whereBetween('submitted_at', [$from, $to])
            ->where(fn (Builder $query) => $this->whereAssigned($query, $user, $role))
            ->when($serviceType, fn (Builder $query) => $query->where('service_type', $serviceType->value))
            ->when($status, fn (Builder $query) => $query->where('status', $status->value));
    }

    private function whereAssigned(Builder $query, User $user, UserRole $role): void
    {
        if ($role === UserRole::CmcStaff) {
            $query->where('cmc_staff_id', $user->id)
                ->orWhereHas('workflowSteps', fn (Builder $query) => $query
                    ->where('stage', WorkflowStepType::CmcAssessment->value)
                    ->where('assigned_to', $user->id));

            return;
        }

        $query->whereHas('workflowSteps', fn (Builder $query) => $query
            ->whereIn('stage', $this->workflowStages($role))
            ->where('assigned_to', $user->id));

        if ($legacyStage = $this->legacyStage($role)) {
            $query->orWhereHas('approvals', fn (Builder $query) => $query
                ->where('role', $legacyStage->value)
                ->where('assigned_to', $user->id));
        }
    }

    /** @param list<string> $decisions */
    private function whereDecision(Builder $query, User $user, UserRole $role, array $decisions): void
    {
        $query->whereHas('workflowSteps', fn (Builder $query) => $query
            ->whereIn('stage', $this->workflowStages($role))
            ->where('assigned_to', $user->id)
            ->whereIn('decision', $decisions));

        if ($legacyStage = $this->legacyStage($role)) {
            $query->orWhereHas('approvals', fn (Builder $query) => $query
                ->where('role', $legacyStage->value)
                ->where('assigned_to', $user->id)
                ->whereIn('decision', $decisions));
        }
    }

    /** @return list<string> */
    private function workflowStages(UserRole $role): array
    {
        return match ($role) {
            UserRole::ManagerOperations => [WorkflowStepType::AmCmcToScrutiny->value, WorkflowStepType::AmCmcToDgm->value],
            UserRole::CmcTeamLead => [WorkflowStepType::CmcAssignment->value],
            UserRole::CmcStaff => [WorkflowStepType::CmcAssessment->value],
            UserRole::DeputyGeneralManager => [WorkflowStepType::DgmApproval->value],
            default => [],
        };
    }

    private function legacyStage(UserRole $role): ?WorkflowStage
    {
        return match ($role) {
            UserRole::ManagerOperations => WorkflowStage::OperationsManager,
            UserRole::DeputyGeneralManager => WorkflowStage::DeputyGeneralManager,
            default => null,
        };
    }
}
