<?php

namespace App\Http\Controllers;

use App\Enums\DeficiencyType;
use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Http\Requests\ListMemosRequest;
use App\Http\Requests\StoreAndSubmitMemoRequest;
use App\Http\Requests\StoreMemoRequest;
use App\Http\Requests\SubmitMemoRequest;
use App\Http\Requests\UpdateMemoRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use App\Services\MasterDataService;
use App\Services\ServiceDeficiencyMemoService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceDeficiencyMemoController extends Controller
{
    public function __construct(
        private ServiceDeficiencyMemoService $memos,
        private MasterDataService $masterData,
        private UserService $users,
    ) {}

    public function index(ListMemosRequest $request): View
    {
        Gate::authorize('viewAny', ServiceDeficiencyMemo::class);

        $pendingStage = $request->pendingStage();

        return view('sdm-memos.index', [
            'memos' => $this->memos->paginateFor($request->user(), $pendingStage),
            'pendingStage' => $pendingStage,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ServiceDeficiencyMemo::class);

        return $this->formView($request);
    }

    public function store(StoreMemoRequest $request): RedirectResponse
    {
        $memo = $this->memos->createDraft($request->user(), $request->validated());

        return redirect()->route('sdm-memos.edit', $memo)->with('status', 'Draft saved.');
    }

    public function storeAndSubmit(StoreAndSubmitMemoRequest $request): RedirectResponse
    {
        $memo = $this->memos->createSubmitted($request->user(), $request->validated());

        return redirect()->route('sdm-memos.show', $memo)->with('status', 'SDM memo submitted successfully.');
    }

    public function show(Request $request, ServiceDeficiencyMemo $sdmMemo): View
    {
        Gate::authorize('view', $sdmMemo);
        $sdmMemo->load([
            'creator:id,name', 'deficiencies.lateReporting', 'publicComplaint.station', 'cashCheck', 'attachments',
            'approvals.assignee', 'approvals.actor', 'approvals.attachments', 'serviceContract.penaltyClauses',
            'staffAcknowledgement', 'workflowSteps.assignee', 'currentWorkflowStep', 'cmcStaff', 'assessments.items',
            'penaltyLetters', 'currentPenaltyLetter', 'objections', 'deduction',
            'cancellationApproval', 'cancellationWorkflowStep',
        ]);

        return view('sdm-memos.show', [
            'memo' => $sdmMemo,
            'cmcStaffUsers' => ($sdmMemo->currentWorkflowStep?->stage === WorkflowStepType::CmcAssignment
                && $request->user()->can('act', $sdmMemo)) || $request->user()->can('assignCmcStaff', $sdmMemo)
                ? $this->users->enabledForRole(UserRole::CmcStaff)
                : collect(),
            'priorApprovedCases' => filled($sdmMemo->employee_number) ? ServiceDeficiencyMemo::query()
                ->whereKeyNot($sdmMemo->id)
                ->where('employee_number', $sdmMemo->employee_number)
                ->whereHas('assessments', fn ($query) => $query->where('status', 'approved'))
                ->with(['assessments' => fn ($query) => $query->where('status', 'approved')])
                ->latest('incident_date')->limit(10)->get() : collect(),
        ]);
    }

    public function edit(Request $request, ServiceDeficiencyMemo $sdmMemo): View
    {
        Gate::authorize('update', $sdmMemo);
        $sdmMemo->load(['deficiencies.lateReporting', 'publicComplaint', 'cashCheck', 'attachments']);

        return $this->formView($request, $sdmMemo);
    }

    public function update(UpdateMemoRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $wasSubmitted = $sdmMemo->submitted_at !== null;
        $this->memos->updateDraft($sdmMemo, $request->user(), $request->validated());

        return back()->with('status', $wasSubmitted ? 'SDM updated.' : 'Draft saved.');
    }

    public function submit(SubmitMemoRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $memo = $this->memos->submit($sdmMemo, $request->user(), $request->validated());

        return redirect()->route('sdm-memos.show', $memo)->with('status', 'SDM memo submitted successfully.');
    }

    public function destroy(ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        Gate::authorize('delete', $sdmMemo);
        $this->memos->deleteDraft($sdmMemo);

        return redirect()->route('sdm-memos.index')->with('status', 'Draft deleted.');
    }

    private function formView(Request $request, ?ServiceDeficiencyMemo $memo = null): View
    {
        $stations = $this->masterData->stations(true);
        $selectedStationId = $memo?->station_id
            ?? $request->session()->get('auth.station_id', $request->user()->station_id);
        $station = $stations->firstWhere('id', (int) $selectedStationId);
        abort_unless($station, 422, 'Select an active station when signing in.');

        $forwardingRole = match (true) {
            $request->user()->hasRole(UserRole::StationManager->value) => UserRole::AssistantManager,
            ! $request->user()->hasRole(UserRole::Administrator->value)
                && $request->user()->can(UserPermission::SubmitOwnDraftMemos->value) => UserRole::StationManager,
            default => null,
        };

        return view('sdm-memos.form', [
            'memo' => $memo,
            'station' => $station,
            'contractors' => $this->masterData->contractors(true),
            'stations' => $stations,
            'issuanceStations' => collect(),
            'sdmTypes' => SdmType::cases(),
            'deficiencyTypes' => DeficiencyType::cases(),
            'forwardingRole' => $forwardingRole,
            'forwardingCandidates' => $forwardingRole
                ? $this->users->enabledForRole($forwardingRole)->filter(fn (User $user): bool => filled($user->employee_number))->values()
                : collect(),
        ]);
    }
}
