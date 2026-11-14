<?php

namespace App\Http\Controllers;

use App\Enums\SdmType;
use App\Http\Requests\UpdateContractStationMappingRequest;
use App\Models\ServiceContract;
use App\Models\Station;
use App\Services\ContractStationMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContractStationMappingController extends Controller
{
    private const STATUS_TABS = ['published' => 'Published', 'retired' => 'Retired / previous'];

    public function __construct(private ContractStationMappingService $mappings) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ServiceContract::class);

        $serviceType = $this->serviceFilter($request);
        $stationSearch = $this->stationSearch($request);
        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && isset(self::STATUS_TABS[$requestedStatus]) ? $requestedStatus : 'published';

        return view('contract-station-mapping.index', [
            ...$this->mappings->index($status, $serviceType, $stationSearch),
            'status' => $status,
            'statusTabs' => self::STATUS_TABS,
            'serviceType' => $serviceType,
            'serviceTypes' => SdmType::cases(),
            'stationSearch' => $stationSearch,
        ]);
    }

    public function edit(Request $request, Station $station, ServiceContract $contract): View|RedirectResponse
    {
        Gate::authorize('update', $contract);

        $listQuery = $this->listQuery($request);
        if (! $this->mappings->canReassign($station, $contract)) {
            return redirect()->route('contract-station-mapping.index', $listQuery)
                ->withErrors(['mapping' => 'This station assignment is no longer available to edit.']);
        }

        return view('contract-station-mapping.edit', [
            'station' => $station,
            'contract' => $contract,
            'replacements' => $this->mappings->replacements($contract),
            'listQuery' => $listQuery,
        ]);
    }

    public function update(UpdateContractStationMappingRequest $request, Station $station, ServiceContract $contract): RedirectResponse
    {
        $this->mappings->reassign($station, $contract, $request->integer('replacement_contract_id'), $request->user());

        return redirect()->route('contract-station-mapping.index', $this->listQuery($request))
            ->with('status', 'Station contract mapping updated.');
    }

    private function serviceFilter(Request $request): ?SdmType
    {
        $type = $request->query('service_type');

        return is_string($type) ? SdmType::tryFrom($type) : null;
    }

    private function listQuery(Request $request): array
    {
        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT);

        return array_filter([
            'service_type' => $this->serviceFilter($request)?->value,
            'station_search' => $this->stationSearch($request),
            'page' => $page > 1 ? $page : null,
        ], fn ($value): bool => $value !== null);
    }

    private function stationSearch(Request $request): ?string
    {
        $search = $request->query('station_search');

        return is_string($search) && trim($search) !== '' ? trim($search) : null;
    }
}
