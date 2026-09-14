<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\StationRepositoryInterface;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\RoleService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private UserService $users,
        private RoleService $roles,
        private StationRepositoryInterface $stations,
    ) {}

    public function index(ListUsersRequest $request, string $role): View
    {
        $userRole = $this->roles->findManagedByNameOrFail($role);

        return view('users.index', [
            'role' => $userRole,
            'users' => $this->users->paginate(
                $userRole->name,
                $request->string('search')->trim()->toString() ?: null,
                $request->validated('status'),
            ),
        ]);
    }

    public function create(string $role): View
    {
        Gate::authorize('create', User::class);
        $roles = $this->roles->managedRoles();

        return $this->createView(
            $this->roles->findManagedByNameOrFail($role),
            $roles,
            false,
        );
    }

    public function createAny(): View
    {
        Gate::authorize('create', User::class);
        $roles = $this->roles->managedRoles();
        $selectedRole = $roles->first();

        abort_if($selectedRole === null, 404, 'Create a role before creating a user.');

        return $this->createView($selectedRole, $roles, true);
    }

    /** @param Collection<int, Role> $roles */
    private function createView(Role $selectedRole, Collection $roles, bool $generalCreate): View
    {
        $stations = $this->stations->activeWithLines();

        return view('users.create', [
            'selectedRole' => $selectedRole,
            'roles' => $roles,
            'stations' => $stations,
            'lines' => $stations->pluck('metroLine')->filter()->unique('id')->sortBy('name')->values(),
            'directStationRoleNames' => $this->roles->directStationRoleNames($roles),
            'generalCreate' => $generalCreate,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());
        $role = $request->validated('role');

        return redirect()->route('users.index', $role)
            ->with('status', "{$user->name} was created successfully.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);
        $user->loadMissing(['roles', 'station.metroLine']);
        $stations = $this->stations->activeWithLines();
        $roles = $this->roles->managedRoles();

        return view('users.edit', [
            'managedUser' => $user,
            'selectedRole' => $this->roles->findManagedByNameOrFail($user->roles->sole()->name),
            'roles' => $roles,
            'stations' => $stations,
            'lines' => $stations->pluck('metroLine')->filter()->unique('id')->sortBy('name')->values(),
            'directStationRoleNames' => $this->roles->directStationRoleNames($roles),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $updatedUser = $this->users->update($user, $request->validated());
        $role = $request->validated('role');

        return redirect()->route('users.index', $role)
            ->with('status', "{$updatedUser->name} was updated successfully.");
    }

    public function status(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $updatedUser = $this->users->setEnabled($user, $request->boolean('is_enabled'));
        $state = $updatedUser->is_enabled ? 'enabled' : 'disabled';

        return back()->with('status', "{$updatedUser->name} was {$state}.");
    }
}
