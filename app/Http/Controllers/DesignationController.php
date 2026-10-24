<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\SaveDesignationRequest;
use App\Models\Designation;
use App\Services\DesignationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DesignationController extends Controller
{
    public function __construct(private DesignationService $designations) {}

    public function index(): View
    {
        Gate::authorize(UserPermission::ManageRoles->value);

        return view('designations.index', ['designations' => $this->designations->all()]);
    }

    public function store(SaveDesignationRequest $request): RedirectResponse
    {
        $designation = $this->designations->create($request->validated(), $request->user());

        return back()->with('status', "{$designation->name} was added successfully.");
    }

    public function destroy(Request $request, Designation $designation): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageRoles->value);
        $this->designations->delete($designation, $request->user());

        return back()->with('status', "{$designation->name} was deleted successfully.");
    }
}
