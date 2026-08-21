<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Services\RoleService;
use App\Services\ServiceDeficiencyMemoService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private UserService $users,
        private RoleService $roles,
        private ServiceDeficiencyMemoService $memos,
    ) {}

    public function __invoke(Request $request): View
    {
        $canManageUsers = $request->user()->can(UserPermission::ViewUsers->value);
        $canUseMemos = $request->user()->canAny([
            UserPermission::ViewOwnMemos->value,
            UserPermission::ViewAllMemos->value,
        ]);

        $roles = $canManageUsers ? $this->roles->managedRoles() : collect();
        $isAdministrator = $request->user()->hasRole(UserRole::Administrator->value);

        return view('dashboard', [
            'canManageUsers' => $canManageUsers,
            'roles' => $roles,
            'roleCounts' => $canManageUsers ? $this->users->managedRoleCounts($roles->pluck('name')->all()) : [],
            'canUseMemos' => $canUseMemos,
            'pendingMemoStages' => $isAdministrator ? $this->memos->pendingByStage() : [],
            'workflowStages' => $isAdministrator ? WorkflowStage::cases() : [],
        ]);
    }
}
