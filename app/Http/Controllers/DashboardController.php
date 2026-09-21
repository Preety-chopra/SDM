<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Http\Requests\DashboardRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\RoleService;
use App\Services\ServiceDeficiencyMemoService;
use App\Services\StationControllerDashboardService;
use App\Services\UserService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private UserService $users,
        private RoleService $roles,
        private ServiceDeficiencyMemoService $memos,
        private StationControllerDashboardService $stationControllerDashboard,
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
        ]);
    }
}
