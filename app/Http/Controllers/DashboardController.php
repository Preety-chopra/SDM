<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Http\Requests\DashboardRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\LineManagerDashboardService;
use App\Services\RoleService;
use App\Services\ServiceDeficiencyMemoService;
use App\Services\StationControllerDashboardService;
use App\Services\StationManagerDashboardService;
use App\Services\UserService;
use App\Services\WorkflowRoleDashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private UserService $users,
        private RoleService $roles,
        private ServiceDeficiencyMemoService $memos,
        private StationControllerDashboardService $stationControllerDashboard,
        private StationManagerDashboardService $stationManagerDashboard,
        private LineManagerDashboardService $lineManagerDashboard,
        private WorkflowRoleDashboardService $workflowRoleDashboard,
    ) {}

    public function __invoke(DashboardRequest $request): View
    {
        $canManageUsers = $request->user()->can(UserPermission::ViewUsers->value);
        $canUseMemos = $request->user()->can('viewAny', ServiceDeficiencyMemo::class);

        $roles = $canManageUsers ? $this->roles->managedRoles() : collect();
        $isAdministrator = $request->user()->hasRole(UserRole::Administrator->value);
        $isStationController = $request->user()->getRoleNames()->contains(
            fn (string $role): bool => in_array($role, [UserRole::Station->value, 'station_controller'], true),
        );
        $isStationManager = $request->user()->hasRole(UserRole::StationManager->value);
        $isLineManager = $request->user()->hasRole(UserRole::AssistantManager->value);
        $workflowDashboardRole = collect([
            UserRole::ManagerOperations,
            UserRole::CmcTeamLead,
            UserRole::CmcStaff,
            UserRole::DeputyGeneralManager,
        ])->first(fn (UserRole $role): bool => $request->user()->hasRole($role->value));

        return view('dashboard', [
            'canManageUsers' => $canManageUsers,
            'roles' => $roles,
            'roleCounts' => $canManageUsers ? $this->users->managedRoleCounts($roles->pluck('name')->all()) : [],
            'canUseMemos' => $canUseMemos,
            'pendingMemoStages' => $isAdministrator ? $this->memos->pendingByStage() : [],
            'workflowStages' => $isAdministrator ? WorkflowStage::cases() : [],
            'pendingWorkflowSteps' => $isAdministrator ? $this->memos->pendingByWorkflowStep() : [],
            'workflowStepTypes' => $isAdministrator ? WorkflowStepType::cases() : [],
            'isStationController' => $isStationController,
            'isStationManager' => $isStationManager,
            'isLineManager' => $isLineManager,
            'isWorkflowDashboardUser' => $workflowDashboardRole !== null,
            'stationControllerDashboard' => $isStationController
                ? $this->stationControllerDashboard->for(
                    $request->user(),
                    $request->dateFrom(),
                    $request->dateTo(),
                    $request->serviceType(),
                    $request->status(),
                    $request->aging(),
                )
                : null,
            'stationManagerDashboard' => $isStationManager
                ? $this->stationManagerDashboard->for(
                    $request->user(),
                    $request->dateFrom(),
                    $request->dateTo(),
                    $request->serviceType(),
                    $request->status(),
                )
                : null,
            'lineManagerDashboard' => $isLineManager
                ? $this->lineManagerDashboard->for(
                    $request->user(),
                    $request->dateFrom(),
                    $request->dateTo(),
                    $request->serviceType(),
                    $request->status(),
                )
                : null,
            'workflowRoleDashboard' => $workflowDashboardRole
                ? $this->workflowRoleDashboard->for(
                    $request->user(),
                    $workflowDashboardRole,
                    $request->dateFrom(),
                    $request->dateTo(),
                    $request->serviceType(),
                    $request->status(),
                )
                : null,
        ]);
    }
}
