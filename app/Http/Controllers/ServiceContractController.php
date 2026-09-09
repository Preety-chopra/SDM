<?php

namespace App\Http\Controllers;

use App\Enums\SdmType;
use App\Http\Requests\StoreServiceContractRequest;
use App\Http\Requests\UpdateServiceContractRequest;
use App\Models\ServiceContract;
use App\Services\MasterDataService;
use App\Services\ServiceContractService;
use App\Support\PenaltyClauseTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceContractController extends Controller
{
    public function __construct(
        private ServiceContractService $contracts,
        private MasterDataService $masterData,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ServiceContract::class);

        return view('contracts.index', ['contracts' => $this->contracts->paginate()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ServiceContract::class);
        $serviceType = SdmType::tryFrom($request->string('service_type')->toString()) ?? SdmType::Cfs;

        return $this->formView(null, $serviceType);
    }

    public function store(StoreServiceContractRequest $request): RedirectResponse
    {
        $contract = $this->contracts->create($request->user(), $request->validated());

        return redirect()->route('contracts.show', $contract)->with('status', 'Contract draft created.');
    }

    public function show(ServiceContract $contract): View
    {
        Gate::authorize('view', $contract);
        $contract->load(['contractor', 'line', 'station', 'penaltyClauses'])->loadCount('memos');

        return view('contracts.show', ['contract' => $contract]);
    }

    public function edit(ServiceContract $contract): View
    {
        Gate::authorize('update', $contract);
        $contract->load('penaltyClauses');

        return $this->formView($contract, $contract->service_type);
    }

    public function update(UpdateServiceContractRequest $request, ServiceContract $contract): RedirectResponse
    {
        $this->contracts->update($contract, $request->user(), $request->validated());

        return redirect()->route('contracts.show', $contract)->with('status', 'Contract draft updated.');
    }

    public function destroy(Request $request, ServiceContract $contract): RedirectResponse
    {
        Gate::authorize('delete', $contract);
        $this->contracts->delete($contract, $request->user());

        return redirect()->route('contracts.index')->with('status', 'Contract draft deleted.');
    }

    public function publish(Request $request, ServiceContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);
        $this->contracts->publish($contract, $request->user());

        return redirect()->route('contracts.show', $contract)->with('status', 'Contract version published.');
    }

    public function revise(Request $request, ServiceContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);
        $revision = $this->contracts->revise($contract, $request->user());

        return redirect()->route('contracts.edit', $revision)->with('status', 'New draft revision created.');
    }

    public function retire(Request $request, ServiceContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);
        $this->contracts->retire($contract, $request->user());

        return redirect()->route('contracts.show', $contract)->with('status', 'Contract retired.');
    }

    public function source(ServiceContract $contract): StreamedResponse
    {
        Gate::authorize('view', $contract);
        abort_unless(filled($contract->source_document_path), 404);

        return Storage::disk('local')->download($contract->source_document_path, $contract->source_document_name);
    }

    private function formView(?ServiceContract $contract, SdmType $serviceType): View
    {
        $stations = $this->masterData->stations(true)
            ->filter(fn ($station): bool => $station->metroLine?->is_active === true)
            ->values();

        return view('contracts.form', [
            'contract' => $contract,
            'serviceType' => $serviceType,
            'serviceTypes' => SdmType::cases(),
            'contractors' => $this->masterData->contractors(true),
            'lines' => $this->masterData->lines(true),
            'stations' => $stations,
            'clauses' => $contract?->penaltyClauses?->map->getAttributes()->all() ?? PenaltyClauseTemplates::for($serviceType),
        ]);
    }
}
