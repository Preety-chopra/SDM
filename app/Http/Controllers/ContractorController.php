<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\SaveContractorRequest;
use App\Models\Contractor;
use App\Services\MasterDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContractorController extends Controller
{
    public function __construct(private MasterDataService $masterData) {}

    public function index(): View
    {
        Gate::authorize(UserPermission::ManageContractors->value);

        return view('master-data.contractors', ['contractors' => $this->masterData->contractors()]);
    }

    public function store(SaveContractorRequest $request): RedirectResponse
    {
        $this->masterData->saveContractor(null, $request->validated());

        return back()->with('status', 'Contractor created.');
    }

    public function update(SaveContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        $this->masterData->saveContractor($contractor, $request->validated());

        return back()->with('status', 'Contractor updated.');
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageContractors->value);
        $this->masterData->deleteContractor($contractor);

        return back()->with('status', 'Contractor deleted.');
    }
}
