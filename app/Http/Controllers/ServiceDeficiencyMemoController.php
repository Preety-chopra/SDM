<?php

namespace App\Http\Controllers;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\StationManagerMemoStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Http\Requests\ListMemosRequest;
use App\Http\Requests\StoreAndSubmitMemoRequest;
use App\Http\Requests\StoreMemoRequest;
use App\Http\Requests\SubmitMemoRequest;
use App\Http\Requests\UpdateMemoLineManagerRequest;
use App\Http\Requests\UpdateMemoRequest;
use App\Http\Requests\UpdateMemoStationManagerRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use App\Services\MasterDataService;
use App\Services\MemoAssessmentService;
use App\Services\MemoTimelineService;
use App\Services\ServiceDeficiencyMemoService;
use App\Services\UserService;
use App\Support\DeficiencyCatalog;
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
        private MemoAssessmentService $assessments,
        private MemoTimelineService $timeline,
    ) {}

    public function index(ListMemosRequest $request): View
    {
        Gate::authorize('viewAny', ServiceDeficiencyMemo::class);

        if ($request->shouldAcknowledgeNotifications()) {
            $request->session()->put('memo_notifications_acknowledged_by', $request->user()->id);
        }

        if ($request->user()->isStationController()) {
            return view('sdm-memos.station-controller.index', [
                'memos' => $this->memos->recentSubmittedBy($request->user()),
            ]);
        }

        $pendingStage = $request->pendingStage();
        $category = $request->category();
        $filters = $request->filters();
        $isStationManager = $request->user()->hasRole(UserRole::StationManager->value);
        $isLineManager = $request->user()->hasRole(UserRole::AssistantManager->value);
        $isCmcTeamLead = $request->user()->hasRole(UserRole::CmcTeamLead->value);
        $usesHeaderFilterTable = $request->user()->hasAnyRole([
            UserRole::StationManager->value,
            UserRole::AssistantManager->value,
            UserRole::ManagerOperations->value,
            UserRole::CmcTeamLead->value,
            UserRole::CmcStaff->value,
            UserRole::DeputyGeneralManager->value,
        ]);
        $isDownstreamWorkflowUser = $request->user()->hasAnyRole([
            UserRole::ManagerOperations->value,
            UserRole::CmcTeamLead->value,
            UserRole::CmcStaff->value,
            UserRole::DeputyGeneralManager->value,
        ]);
        $statuses = match (true) {
            $isStationManager => StationManagerMemoStatus::cases(),
            $isLineManager => MemoStatus::lineManagerFilterCases(),
            $isDownstreamWorkflowUser => collect(MemoStatus::cases())
                ->reject(fn (MemoStatus $status): bool => $status === MemoStatus::Draft)
                ->values()
                ->all(),
            default => MemoStatus::cases(),
        };
        $memoCategoryTabs = [
            ['category' => null, 'label' => 'All Memos'],
            ['category' => 'pending', 'label' => 'Pending Memos'],
        ];

        if (! $isDownstreamWorkflowUser) {
            $memoCategoryTabs[] = ['category' => 'transferred', 'label' => 'Transferred Memos'];
        }

        $memos = $this->memos->paginateFor($request->user(), $filters, $pendingStage, $category);
        $bulkForwardingEnabled = $category === null
            && $request->user()->can('bulkForward', ServiceDeficiencyMemo::class);
        $bulkForwardDestinations = $bulkForwardingEnabled
            ? $memos->getCollection()
                ->filter(fn (ServiceDeficiencyMemo $memo): bool => $memo->currentWorkflowStep?->assigned_to === $request->user()->id
                    && $memo->currentWorkflowStep?->decision === 'pending'
                    && in_array($memo->currentWorkflowStep?->stage, [
                        WorkflowStepType::AmCmcToScrutiny,
                        WorkflowStepType::AmCmcToDgm,
                    ], true))
                ->mapWithKeys(fn (ServiceDeficiencyMemo $memo): array => [
                    (string) $memo->id => $memo->currentWorkflowStep->stage->value,
                ])
            : collect();
        $bulkCmcActionsEnabled = $category === null
            && $request->user()->can('bulkCmcAction', ServiceDeficiencyMemo::class);
        $bulkCmcActions = $bulkCmcActionsEnabled
            ? $memos->getCollection()->mapWithKeys(function (ServiceDeficiencyMemo $memo) use ($request): array {
                $step = $memo->currentWorkflowStep;
                $actions = match (true) {
                    $step?->assigned_to !== $request->user()->id || $step?->decision !== 'pending' => [],
                    $step->stage === WorkflowStepType::CmcAssignment => ['assign'],
                    $step->stage === WorkflowStepType::CmcVerification => ['forward', 'return'],
                    default => [],
                };

                return [(string) $memo->id => $actions];
            })
            : collect();
        $showActionColumn = ! $isStationManager
            && $memos->getCollection()->contains(
                fn (ServiceDeficiencyMemo $memo): bool => $this->hasVisibleTableAction(
                    $request->user(),
                    $memo,
                    $isCmcTeamLead,
                ),
            );

        return view('sdm-memos.index', [
            'memos' => $memos,
            'pendingStage' => $pendingStage,
            'category' => $category,
            'filters' => $filters,
            'stationManagerStatusView' => $isStationManager,
            'lineManagerStatusView' => $isLineManager,
            'cmcTeamLeadTable' => $isCmcTeamLead,
            'headerFilterTable' => $usesHeaderFilterTable,
            'sdmTypes' => SdmType::cases(),
            'statuses' => $statuses,
            'stations' => $this->masterData->stations(),
            'lines' => $this->masterData->lines(),
            'bulkForwardingEnabled' => $bulkForwardingEnabled,
            'bulkForwardDestinations' => $bulkForwardDestinations,
            'bulkCmcActionsEnabled' => $bulkCmcActionsEnabled,
            'bulkCmcActions' => $bulkCmcActions,
            'showActionColumn' => $showActionColumn,
            'cmcStaffUsers' => $bulkCmcActionsEnabled
                ? $this->users->enabledForRole(UserRole::CmcStaff)
                : collect(),
            'memoCategoryTabs' => $memoCategoryTabs,
        ]);
    }

    private function hasVisibleTableAction(User $user, ServiceDeficiencyMemo $memo, bool $isCmcTeamLead): bool
    {
        $currentStep = $memo->currentWorkflowStep;

        if ($isCmcTeamLead
            && $currentStep?->assigned_to === $user->id
            && $currentStep->decision === 'pending'
            && in_array($currentStep->stage, [WorkflowStepType::CmcAssignment, WorkflowStepType::CmcVerification], true)) {
            return true;
        }

        if ($user->can('delete', $memo)) {
            return true;
        }

        $isDirectCancellationStage = $memo->workflow_version >= 2
            ? $currentStep?->stage === WorkflowStepType::LineReview
            : $memo->current_stage === WorkflowStage::LineManager;

        return $isDirectCancellationStage && $user->can('act', $memo);
    }

    public function drafts(Request $request): View
    {
        abort_unless($request->user()->isStationController(), 403);
        Gate::authorize('viewAny', ServiceDeficiencyMemo::class);

        return view('sdm-memos.station-controller.drafts', [
            'memos' => $this->memos->paginateDraftsBy($request->user()),
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

        if ($request->user()->isStationController()) {
            return redirect()->route('sdm-memos.index')->with('status', 'SDM memo submitted successfully.');
        }

        return redirect()->route('sdm-memos.show', $memo)->with('status', 'SDM memo submitted successfully.');
    }

    public function show(Request $request, ServiceDeficiencyMemo $sdmMemo): View
    {
        Gate::authorize('view', $sdmMemo);
        $sdmMemo->load([
            'creator:id,name,employee_number', 'deficiencies.lateReporting', 'publicComplaint.station', 'cashCheck', 'attachments',
            'approvals.assignee', 'approvals.actor', 'approvals.attachments', 'serviceContract.penaltyClauses',
            'staffAcknowledgement', 'workflowSteps.assignee', 'currentWorkflowStep', 'cmcStaff', 'assessments.items',
            'penaltyLetters', 'currentPenaltyLetter', 'objections', 'deduction',
            'cancellationApproval', 'cancellationWorkflowStep',
        ]);

        $timelineSteps = $this->timeline->visibleSteps($sdmMemo, $request->user());

        $timelineApprovals = match (true) {
            $request->user()->isStationController() => $sdmMemo->approvals->filter(
                fn ($approval): bool => $approval->role === WorkflowStage::StationManager,
            ),
            $request->user()->hasRole(UserRole::StationManager->value) => $sdmMemo->approvals->filter(fn ($approval): bool => in_array($approval->role, [
                WorkflowStage::StationManager,
                WorkflowStage::LineManager,
            ], true)),
            default => $sdmMemo->approvals,
        };

        $stationManagerConfirmationPreview = [
            ['label' => 'Line', 'value' => $sdmMemo->line_name ?: '—'],
            ['label' => 'Station', 'value' => $sdmMemo->station_name ?: '—'],
            ['label' => 'SDM type', 'value' => $sdmMemo->service_type->label()],
            ['label' => 'Selected Deficiency', 'value' => $sdmMemo->deficiencies->map(fn ($deficiency) => $deficiency->type->label())->join(', ') ?: '—'],
            ['label' => 'Incident date and time', 'value' => collect([
                $sdmMemo->incident_date?->format('d-m-Y'),
                filled($sdmMemo->incident_time) ? substr($sdmMemo->incident_time, 0, 5) : null,
            ])->filter()->join(' ') ?: '—'],
            ['label' => 'Submitted on', 'value' => $sdmMemo->submitted_at?->format('d-m-Y H:i') ?? '—'],
        ];

        return view('sdm-memos.show', [
            'memo' => $sdmMemo,
            'timelineSteps' => $timelineSteps,
            'timelineApprovals' => $timelineApprovals,
            'stationManagerConfirmationPreview' => $stationManagerConfirmationPreview,
            'showContractAssessment' => ! $request->user()->isStationController()
                && ! $request->user()->hasRole(UserRole::StationManager->value),
            'showCmcStaffDetails' => $request->user()->hasAnyRole([
                UserRole::ManagerOperations->value,
                UserRole::CmcTeamLead->value,
                UserRole::CmcStaff->value,
                UserRole::DeputyGeneralManager->value,
            ]),
            'lineReviewPenaltyPrefillItems' => $sdmMemo->currentWorkflowStep?->stage === WorkflowStepType::LineReview
                ? $this->assessments->lineReviewPrefill($sdmMemo)
                : collect(),
            'cmcNegativePointsPrefill' => $sdmMemo->currentWorkflowStep?->stage === WorkflowStepType::CmcAssessment
                ? $this->assessments->negativePointsPrefill($sdmMemo)
                : null,
            'stationManagerCandidates' => $request->user()->can('reassignStationManager', $sdmMemo)
                ? $this->users->enabledForRole(UserRole::StationManager)
                    ->filter(fn (User $user): bool => filled($user->employee_number) && $user->id !== $request->user()->id)
                    ->values()
                : collect(),
            'lineManagerCandidates' => ($sdmMemo->current_stage === WorkflowStage::StationManager
                || $sdmMemo->currentWorkflowStep?->stage === WorkflowStepType::StationManager)
                && $request->user()->can('act', $sdmMemo)
                    ? $this->users->enabledForRole(UserRole::AssistantManager)
                        ->filter(fn (User $user): bool => filled($user->employee_number))
                        ->values()
                    : collect(),
            'lineManagerHandoverCandidates' => $request->user()->can('handoverLineManager', $sdmMemo)
                ? $this->users->enabledForRole(UserRole::AssistantManager)
                    ->filter(fn (User $user): bool => filled($user->employee_number) && $user->id !== $request->user()->id)
                    ->values()
                : collect(),
            'cmcTeamLeadCandidates' => $request->user()->can('reassignCmcTeamLead', $sdmMemo)
                ? $this->users->enabledForRole(UserRole::CmcTeamLead)
                    ->reject(fn (User $user): bool => $user->id === $sdmMemo->currentWorkflowStep?->assigned_to)
                    ->values()
                : collect(),
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

        if ($wasSubmitted && $request->user()->hasRole(UserRole::StationManager->value)) {
            return redirect()
                ->route('sdm-memos.show', $sdmMemo)
                ->with('status', 'SDM updated.');
        }

        return back()->with('status', $wasSubmitted ? 'SDM updated.' : 'Draft saved.');
    }

    public function updateStationManager(UpdateMemoStationManagerRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->memos->reassignStationManager(
            $sdmMemo,
            $request->user(),
            $request->string('station_manager_employee_number')->toString(),
        );

        $message = $request->user()->hasRole(UserRole::StationManager->value)
            ? 'Pending SDM handed over successfully.'
            : 'Forwarded Station Manager changed.';

        return redirect()->route('sdm-memos.show', $sdmMemo)->with('status', $message);
    }

    public function updateLineManager(UpdateMemoLineManagerRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->memos->handoverLineManager(
            $sdmMemo,
            $request->user(),
            $request->string('line_manager_employee_number')->toString(),
        );

        return redirect()->route('sdm-memos.show', $sdmMemo)->with('status', 'Pending SDM handed over successfully.');
    }

    public function submit(SubmitMemoRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $memo = $this->memos->submit($sdmMemo, $request->user(), $request->validated());

        if ($request->user()->isStationController()) {
            return redirect()->route('sdm-memos.index')->with('status', 'SDM memo submitted successfully.');
        }

        return redirect()->route('sdm-memos.show', $memo)->with('status', 'SDM memo submitted successfully.');
    }

    public function destroy(ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        Gate::authorize('delete', $sdmMemo);
        $this->memos->deleteDraft($sdmMemo);

        if (request()->user()->isStationController()) {
            return redirect()->route('sdm-memos.drafts')->with('status', 'Draft deleted.');
        }

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
            'deficiencyOptionsByType' => DeficiencyCatalog::grouped(),
            'forwardingRole' => $forwardingRole,
            'forwardingCandidates' => $forwardingRole
                ? $this->users->enabledForRole($forwardingRole)->filter(fn (User $user): bool => filled($user->employee_number))->values()
                : collect(),
        ]);
    }
}
