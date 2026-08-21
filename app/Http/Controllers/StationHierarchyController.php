<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\UpdateStationHierarchyRequest;
use App\Services\StationHierarchyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StationHierarchyController extends Controller
{
    public function __construct(private StationHierarchyService $hierarchies) {}

    public function index(): View
    {
        Gate::authorize(UserPermission::ManageSdmHierarchy->value);

        return view('hierarchies.index', [
            'hierarchies' => $this->hierarchies->all(),
            'mappingLevels' => $this->hierarchies->mappingLevels(),
            'reportingRelationships' => $this->hierarchies->reportingRelationships(),
        ]);
    }

    public function update(UpdateStationHierarchyRequest $request): RedirectResponse
    {
        $this->hierarchies->updateReportingRelationship(
            (int) $request->validated('subordinate_id'),
            (int) $request->validated('supervisor_id'),
        );

        return back()->with('status', 'Reporting relationship updated.');
    }
}
