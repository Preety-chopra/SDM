<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\SaveStationRequest;
use App\Models\Station;
use App\Services\MasterDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StationController extends Controller
{
    public function __construct(private MasterDataService $masterData) {}

    public function index(): View
    {
        Gate::authorize(UserPermission::ManageStations->value);

        return view('master-data.stations', ['stations' => $this->masterData->stations(), 'lines' => $this->masterData->lines(true)]);
    }

    public function store(SaveStationRequest $request): RedirectResponse
    {
        $this->masterData->saveStation(null, $request->validated());

        return back()->with('status', 'Station created.');
    }

    public function update(SaveStationRequest $request, Station $station): RedirectResponse
    {
        $this->masterData->saveStation($station, $request->validated());

        return back()->with('status', 'Station updated.');
    }

    public function destroy(Station $station): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageStations->value);
        $this->masterData->deleteStation($station);

        return back()->with('status', 'Station deleted.');
    }
}
