<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\StationRepositoryInterface;
use App\Enums\UserRole;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private UserService $users,
        private StationRepositoryInterface $stations,
    ) {}

    public function index(ListUsersRequest $request, string $role): View
    {
        $userRole = UserRole::managedFrom($role);

        return view('users.index', [
            'role' => $userRole,
            'users' => $this->users->paginate(
                $userRole,
                $request->string('search')->trim()->toString() ?: null,
                $request->validated('status'),
            ),
        ]);
    }

    public function create(string $role): View
    {
        Gate::authorize('create', User::class);
        $stations = $this->stations->activeWithLines();

        return view('users.create', [
            'selectedRole' => UserRole::managedFrom($role),
            'roles' => UserRole::managed(),
            'stations' => $stations,
            'lines' => $stations->pluck('metroLine')->filter()->unique('id')->sortBy('name')->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());
        $role = UserRole::from($request->validated('role'));

        return redirect()->route('users.index', $role->value)
            ->with('status', "{$user->name} was created successfully.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);
        $user->loadMissing(['roles', 'station.metroLine']);
        $stations = $this->stations->activeWithLines();

        return view('users.edit', [
            'managedUser' => $user,
            'selectedRole' => UserRole::from($user->roles->sole()->name),
            'roles' => UserRole::managed(),
            'stations' => $stations,
            'lines' => $stations->pluck('metroLine')->filter()->unique('id')->sortBy('name')->values(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $updatedUser = $this->users->update($user, $request->validated());
        $role = UserRole::from($request->validated('role'));

        return redirect()->route('users.index', $role->value)
            ->with('status', "{$updatedUser->name} was updated successfully.");
    }

    public function status(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $updatedUser = $this->users->setEnabled($user, $request->boolean('is_enabled'));
        $state = $updatedUser->is_enabled ? 'enabled' : 'disabled';

        return back()->with('status', "{$updatedUser->name} was {$state}.");
    }
}
