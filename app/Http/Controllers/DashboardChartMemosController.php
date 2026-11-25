<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\DashboardChartMemosRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\StationControllerDashboardService;
use App\Services\WorkflowRoleDashboardService;
use App\Support\CmcSdmTableRow;
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
            UserRole::DgmAft,
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
            'memos' => $this->serialize($memos->take(100), $workflowRole),
            'has_more' => $memos->count() > 100,
        ]);
    }

    /**
     * @param  Collection<int, ServiceDeficiencyMemo>  $memos
     * @return Collection<int, array<string, int|string|null>>
     */
    private function serialize(Collection $memos, ?UserRole $workflowRole): Collection
    {
        $cmcSdmTable = in_array($workflowRole, UserRole::cmcRoles(), true);
        $highlightStaffProposal = in_array($workflowRole, UserRole::cmcReviewers(), true);

        return $memos->map(fn (ServiceDeficiencyMemo $memo): array => [
            'id' => $memo->id,
            'service' => $memo->service_type->code(),
            'line' => $memo->line_code ?: 'Not recorded',
            'station' => $memo->station_name ?: 'Not recorded',
            'status' => $workflowRole === null
                ? $this->stationDashboard->visibleStatusLabel($memo->status)
                : $memo->status->label(),
            'submitted_at' => ($memo->submitted_at ?? $memo->created_at)?->format('d M Y'),
            'url' => route('sdm-memos.show', $memo),
            ...($cmcSdmTable ? CmcSdmTableRow::fromMemo($memo) : []),
            ...($highlightStaffProposal
                ? ['highlighted_proposal' => CmcSdmTableRow::highlightedProposal($memo)] : []),
        ])->values();
    }
}
