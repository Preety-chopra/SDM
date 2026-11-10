<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\AftCashCheckWorkflowRequest;
use App\Http\Requests\SaveAftCashCheckReportRequest;
use App\Http\Requests\SubmitAftCashCheckReportRequest;
use App\Models\AftCashCheckReport;
use App\Services\AftCashCheckReportService;
use App\Services\MasterDataService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AftCashCheckReportController extends Controller
{
    public function __construct(private AftCashCheckReportService $reports, private MasterDataService $masterData, private UserService $users) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AftCashCheckReport::class);

        return view('aft-cash-check-reports.index', [
            'reports' => $this->reports->paginateFor($request->user(), $request->boolean('pending')),
            'pending' => $request->boolean('pending'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', AftCashCheckReport::class);

        return $this->formView(new AftCashCheckReport);
    }

    public function store(SaveAftCashCheckReportRequest $request): RedirectResponse
    {
        $report = $this->reports->save(null, $request->user(), $request->validated());

        return to_route('aft-cash-check-reports.'.($report->status === 'draft' ? 'edit' : 'show'), $report)
            ->with('status', $report->status === 'draft' ? 'Cash-check report draft saved.' : 'Cash-check report submitted to DGM AFT.');
    }

    public function edit(AftCashCheckReport $aftCashCheckReport): View
    {
        Gate::authorize('update', $aftCashCheckReport);

        return $this->formView($aftCashCheckReport->load('entries'));
    }

    private function formView(AftCashCheckReport $report): View
    {
        return view('aft-cash-check-reports.form', [
            'report' => $report,
            'stations' => $this->masterData->stations(true)->filter(fn ($station) => $station->metroLine?->is_active)->values(),
        ]);
    }

    public function update(SaveAftCashCheckReportRequest $request, AftCashCheckReport $aftCashCheckReport): RedirectResponse
    {
        $report = $this->reports->save($aftCashCheckReport, $request->user(), $request->validated());

        return to_route('aft-cash-check-reports.show', $report)->with('status', $report->status === 'draft' ? 'Cash-check report draft saved.' : 'Cash-check report submitted to DGM AFT.');
    }

    public function submit(SubmitAftCashCheckReportRequest $request, AftCashCheckReport $aftCashCheckReport): RedirectResponse
    {
        $report = $this->reports->save($aftCashCheckReport, $request->user(), $request->validated());

        return to_route('aft-cash-check-reports.show', $report)->with('status', 'Cash-check report submitted to DGM AFT.');
    }

    public function show(AftCashCheckReport $aftCashCheckReport): View
    {
        Gate::authorize('view', $aftCashCheckReport);

        return view('aft-cash-check-reports.show', [
            'report' => $aftCashCheckReport->load(['entries', 'steps', 'currentStep']),
            'cmcStaff' => $this->users->enabledForRole(UserRole::CmcStaff),
        ]);
    }

    public function workflow(AftCashCheckWorkflowRequest $request, AftCashCheckReport $aftCashCheckReport): RedirectResponse
    {
        $report = $this->reports->act($aftCashCheckReport, $request->user(), $request->validated());

        return to_route('aft-cash-check-reports.show', $report)->with('status', 'Cash-check report action recorded.');
    }
}
