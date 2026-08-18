<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private UserService $users) {}

    public function __invoke(Request $request): View
    {
        $canManageUsers = $request->user()->can(UserPermission::ViewUsers->value);
        $canUseMemos = $request->user()->canAny([
            UserPermission::ViewOwnMemos->value,
            UserPermission::ViewAllMemos->value,
        ]);

        return view('dashboard', [
            'canManageUsers' => $canManageUsers,
            'roles' => UserRole::managed(),
            'roleCounts' => $canManageUsers ? $this->users->managedRoleCounts() : [],
            'canUseMemos' => $canUseMemos,
        ]);
    }
}
