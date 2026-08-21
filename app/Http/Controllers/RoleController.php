<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('roles.index', ['roles' => $this->roles->managedRoles()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('roles.create', ['permissions' => $this->roles->permissions()]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = $this->roles->create($request->validated());

        return redirect()->route('roles.index')->with('status', "{$role->name} was created successfully.");
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);
        $role->loadMissing('permissions');

        return view('roles.edit', [
            'managedRole' => $role,
            'permissions' => $this->roles->permissions(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role = $this->roles->update($role, $request->validated());

        return redirect()->route('roles.index')->with('status', "{$role->name} was updated successfully.");
    }
}
