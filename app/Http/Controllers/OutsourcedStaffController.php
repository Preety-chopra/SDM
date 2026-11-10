<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\OutsourcedStaffIndexRequest;
use App\Http\Requests\SaveOutsourcedStaffRequest;
use App\Models\OutsourcedStaff;
use App\Services\MasterDataService;
use App\Services\OutsourcedStaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OutsourcedStaffController extends Controller
{
    public function __construct(private OutsourcedStaffService $staff, private MasterDataService $masterData) {}

    public function index(OutsourcedStaffIndexRequest $request): View
    {
        return view('outsourced-staff.index', [
            'staff' => $this->staff->paginate($request->validated()),
            'contractors' => $this->masterData->contractors(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize(UserPermission::ManageContractors->value);

        return view('outsourced-staff.form', ['staff' => new OutsourcedStaff, 'contractors' => $this->masterData->contractors()]);
    }

    public function store(SaveOutsourcedStaffRequest $request): RedirectResponse
    {
        $this->staff->save(null, $request->validated(), $request->user());

        return to_route('outsourced-staff.index')->with('status', 'Outsourced staff member added.');
    }

    public function edit(OutsourcedStaff $outsourcedStaff): View
    {
        Gate::authorize(UserPermission::ManageContractors->value);

        return view('outsourced-staff.form', ['staff' => $outsourcedStaff, 'contractors' => $this->masterData->contractors()]);
    }

    public function update(SaveOutsourcedStaffRequest $request, OutsourcedStaff $outsourcedStaff): RedirectResponse
    {
        $this->staff->save($outsourcedStaff, $request->validated(), $request->user());

        return to_route('outsourced-staff.index')->with('status', 'Outsourced staff member updated.');
    }
}
