<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\DashboardChartMemosRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\StationControllerDashboardService;
use App\Services\WorkflowRoleDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class DashboardChartMemosController extends Controller
{
    public function __construct(
        private StationControllerDashboardService $stationDashboard,
        private WorkflowRoleDashboardService $workflowDashboard,
    ) {}

    public function __invoke(DashboardChartMemosRequest $request): JsonResponse
    {
        $user = $request->user();
        $workflowRole = collect([
            UserRole::ManagerOperations,
            UserRole::CmcTeamLead,
            UserRole::CmcStaff,
            UserRole::DeputyGeneralManager,
        ])->first(fn (UserRole $role): bool => $user->hasRole($role->value));

        $memos = $workflowRole
            ? $this->workflowDashboard->memosForBar(
                $user,
                $workflowRole,
                $request->dateFrom(),
                $request->dateTo(),
                $request->serviceType(),
                $request->status(),
                101,
            )
            : $this->stationDashboard->memosForBar(
                $user,
                $request->dateFrom(),
                $request->dateTo(),
                $request->serviceType(),
                $request->status(),
                $request->aging(),
                101,
            );

        return response()->json([
            'memos' => $this->serialize($memos->take(100), $workflowRole === null),
            'has_more' => $memos->count() > 100,
        ]);
    }

    /**
     * @param  Collection<int, ServiceDeficiencyMemo>  $memos
     * @return Collection<int, array<string, int|string|null>>
     */
    private function serialize(Collection $memos, bool $maskAfterStationManager): Collection
    {
        return $memos->map(fn (ServiceDeficiencyMemo $memo): array => [
            'id' => $memo->id,
            'service' => $memo->service_type->code(),
            'station' => $memo->station_name ?: 'Not recorded',
            'status' => $maskAfterStationManager
                ? $this->stationDashboard->visibleStatusLabel($memo->status)
                : $memo->status->label(),
            'submitted_at' => ($memo->submitted_at ?? $memo->created_at)?->format('d M Y'),
            'url' => route('sdm-memos.show', $memo),
        ])->values();
    }
}
