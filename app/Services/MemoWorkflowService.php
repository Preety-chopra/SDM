<?php

namespace App\Services;

use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\MemoAssessment;
use App\Models\MemoWorkflowStep;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MemoWorkflowService
{
    public function __construct(
        private MemoAssessmentService $assessments,
        private AuditService $audit,
        private ServiceDeficiencyMemoRepositoryInterface $memos,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @param  array<string, int|string>  $filters
     * @param  list<int>  $memoIds
     */
    public function bulkForward(
        User $actor,
        WorkflowStepType $stage,
        array $filters,
        array $memoIds,
        bool $selectAll,
    ): int {
        return DB::transaction(function () use ($actor, $stage, $filters, $memoIds, $selectAll): int {
            $memos = $this->memos->bulkForwardableFor(
                $actor,
                $stage,
                $selectAll ? $filters : [],
                $selectAll ? [] : $memoIds,
            );

            if ($memos->isEmpty() || (! $selectAll && $memos->count() !== count($memoIds))) {
                throw ValidationException::withMessages([
                    'memo_ids' => 'Select only SDMs currently assigned to you at the chosen AM/CMC stage.',
                ]);
            }

            foreach ($memos as $memo) {
                $this->act($memo, $actor, ['action' => 'forward']);
            }

            return $memos->count();
        });
    }

    public function act(ServiceDeficiencyMemo $memo, User $actor, array $data): ServiceDeficiencyMemo
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($memo, $actor, $data, &$storedPaths): ServiceDeficiencyMemo {
                $memo = ServiceDeficiencyMemo::query()->lockForUpdate()->findOrFail($memo->id);
                if ($memo->workflow_version >= 2) {
                    return $this->actVersionTwo($memo, $actor, $data, $storedPaths);
                }

                $stage = $memo->current_stage;
                $approval = $memo->approvals()->where('role', $stage->value)->lockForUpdate()->firstOrFail();
                $action = $data['action'];
                $isCancellationRequest = $action === 'request_cancellation';

                if ($approval->assigned_to !== $actor->id || $approval->decision !== 'pending') {
                    abort(403);
                }
                if ($stage === WorkflowStage::StationManager && $action === 'cancel') {
                    throw ValidationException::withMessages(['action' => 'Station Manager must request cancellation from AM/Line.']);
                }
                if ($isCancellationRequest && $stage !== WorkflowStage::StationManager) {
                    throw ValidationException::withMessages(['action' => 'Only the Station Manager can request cancellation from AM/Line.']);
                }
                if ($stage === WorkflowStage::DeputyGeneralManager && $action === 'forward') {
                    throw ValidationException::withMessages(['action' => 'DGM/O must approve or cancel the SDM.']);
                }
                if ($stage !== WorkflowStage::DeputyGeneralManager && $action === 'approve') {
                    throw ValidationException::withMessages(['action' => 'This stage must forward or cancel the SDM.']);
                }

                if ($stage === WorkflowStage::StationManager && in_array($action, ['forward', 'request_cancellation'], true)) {
                    $this->assignNextLegacyApproval($memo, $stage, $data['forward_to_employee_number']);
                }

                $approval->update([
                    'decision' => $action === 'cancel' ? 'rejected' : 'approved',
                    'acted_by' => $actor->id,
                    'signer_name' => $actor->name,
                    'signer_employee_number' => $actor->employee_number,
                    'acted_at' => now(),
                    'remarks' => $isCancellationRequest
                        ? $this->cancellationRequestRemarks($data['remarks'] ?? null)
                        : ($data['remarks'] ?? null),
                ]);

                foreach (Arr::wrap($data['evidence'] ?? []) as $file) {
                    if ($file instanceof UploadedFile) {
                        $path = $file->store("sdm-memos/{$memo->id}/workflow/{$approval->id}", 'local');
                        $storedPaths[] = $path;
                        $approval->attachments()->create([
                            'original_name' => $file->getClientOriginalName(),
                            'storage_path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size_bytes' => $file->getSize(),
                            'uploaded_by' => $actor->id,
                        ]);
                    }
                }

                if ($action === 'cancel') {
                    $memo->update(['status' => MemoStatus::Cancelled->value]);
                } elseif ($action === 'approve') {
                    $memo->update(['status' => MemoStatus::Closed->value]);
                } else {
                    $memo->update([
                        'status' => MemoStatus::UnderReview->value,
                        'current_stage' => $stage->next()->value,
                    ]);
                }

                return $memo->refresh()->load(['approvals.assignee', 'approvals.actor', 'approvals.attachments']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }

    private function actVersionTwo(ServiceDeficiencyMemo $memo, User $actor, array $data, array &$storedPaths): ServiceDeficiencyMemo
    {
        $step = MemoWorkflowStep::query()->lockForUpdate()->findOrFail($memo->current_workflow_step_id);
        $action = $data['action'];
        $isCancellationRequest = $action === 'request_cancellation';
        $isCmcTeamLeadVerification = $step->stage === WorkflowStepType::CmcAssessment
            && $actor->is_enabled
            && $actor->hasRole(UserRole::CmcTeamLead->value)
            && $actor->can(UserPermission::ActOnRoutedMemos->value);

        if (($step->assigned_to !== $actor->id && ! $isCmcTeamLeadVerification) || $step->decision !== 'pending') {
            abort(403);
        }

        $isCmcAssignment = $step->stage === WorkflowStepType::CmcAssignment;
        $isDgm = $step->stage === WorkflowStepType::DgmApproval;
        $canCancel = $step->stage === WorkflowStepType::LineReview;
        if ($step->stage === WorkflowStepType::StationManager && $action === 'cancel') {
            throw ValidationException::withMessages(['action' => 'Station Manager must request cancellation from AM/Line.']);
        }
        if ($isCancellationRequest && $step->stage !== WorkflowStepType::StationManager) {
            throw ValidationException::withMessages(['action' => 'Only the Station Manager can request cancellation from AM/Line.']);
        }
        if ($isCmcAssignment && ! in_array($action, ['assign', 'verify'], true)) {
            throw ValidationException::withMessages(['action' => 'The CMC Team Lead must assign CMC Staff or verify the SDM directly.']);
        }
        if (! $isCmcAssignment && $isDgm && ! in_array($action, ['approve', 'return', 'cancel'], true)) {
            throw ValidationException::withMessages(['action' => 'DGM must approve, return, or cancel the SDM.']);
        }
        if (! $isCmcAssignment && ! $isDgm && ! (in_array($action, ['forward', 'request_cancellation'], true) || ($canCancel && $action === 'cancel'))) {
            throw ValidationException::withMessages(['action' => 'This stage must forward the SDM.']);
        }

        if ($step->stage === WorkflowStepType::StationManager && in_array($action, ['forward', 'request_cancellation'], true)) {
            $this->assignNextVersionTwoStep($memo, $step, $data['forward_to_employee_number']);
        }

        $assignedCmcStaff = null;
        if ($isCmcAssignment && $action === 'assign') {
            $assignedCmcStaff = $this->resolveCmcStaff((int) $data['cmc_staff_id']);
            $this->applyCmcStaffAssignment($memo, $actor, $assignedCmcStaff, false);
        } elseif ($isCmcAssignment && $action === 'verify') {
            $this->assignCmcAssessmentToTeamLead($memo, $step, $actor);
        }

        $assessment = null;
        $assessmentAttachment = null;
        if ($action === 'forward' && in_array($step->stage, [WorkflowStepType::LineReview, WorkflowStepType::CmcAssessment], true)) {
            $assessment = $this->assessments->create($memo, $actor, $step->stage, $step->round, $data);

            if ($step->stage === WorkflowStepType::CmcAssessment
                && ($data['assessment_pdf'] ?? null) instanceof UploadedFile) {
                $file = $data['assessment_pdf'];
                $path = $file->store("sdm-memos/{$memo->id}/assessments/{$assessment->id}", 'local');
                $storedPaths[] = $path;
                $assessmentAttachment = $memo->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => $actor->id,
                ]);
            }

            $this->audit->record($memo, $actor, 'memo.assessment.created', [], [
                'assessment_id' => $assessment->id,
                'version' => $assessment->version,
                'round' => $assessment->round,
                'proposed_total' => $assessment->proposed_total,
                'negative_points' => $assessment->negative_points,
                'assessment_pdf_attachment_id' => $assessmentAttachment?->id,
            ]);
        }

        if ($action === 'approve') {
            $approved = $memo->assessments()->where('source_stage', WorkflowStepType::CmcAssessment->value)->reorder('version', 'desc')->firstOrFail();
            if ($approved->cap_warning && ! ($data['cap_acknowledged'] ?? false)) {
                throw ValidationException::withMessages(['cap_acknowledged' => 'DGM acknowledgement is required because the advisory 10% threshold has been reached.']);
            }
            $memo->assessments()->where('status', 'approved')->update(['status' => 'superseded']);
            $approved->update([
                'status' => 'approved',
                'cap_acknowledged' => (bool) ($data['cap_acknowledged'] ?? false),
                'administrative_review_flag' => $this->requiresAdministrativeReview($memo, $approved),
                'approved_at' => now(),
            ]);
        }

        $step->update([
            'decision' => match ($action) {
                'return' => 'returned',
                'cancel' => 'cancelled',
                default => $action === 'approve' ? 'approved' : 'forwarded',
            },
            'acted_by' => $actor->id,
            'actor_name' => $actor->name,
            'actor_employee_number' => $actor->employee_number,
            'remarks' => $isCancellationRequest
                ? $this->cancellationRequestRemarks($data['remarks'] ?? null)
                : ($data['remarks'] ?? null),
            'acted_at' => now(),
        ]);

        if ($action === 'cancel') {
            $memo->update(['status' => MemoStatus::Cancelled->value, 'current_workflow_step_id' => null]);
        } elseif ($action === 'approve') {
            $memo->update(['status' => MemoStatus::PendingPenaltyLetter->value, 'current_workflow_step_id' => null]);
        } elseif ($action === 'return') {
            $this->createReviewRound($memo);
        } else {
            $next = $memo->workflowSteps()->where('sequence', '>', $step->sequence)->where('decision', 'pending')->firstOrFail();
            $memo->update([
                'current_workflow_step_id' => $next->id,
                'status' => $this->statusFor($next->stage)->value,
            ]);
        }

        $this->audit->record($memo, $actor, 'memo.workflow.'.$action, [], [
            'step_id' => $step->id,
            'stage' => $step->stage->value,
            'round' => $step->round,
            'assessment_id' => $assessment?->id,
            'cmc_staff_id' => $assignedCmcStaff?->id,
        ]);

        return $memo->refresh()->load(['currentWorkflowStep', 'workflowSteps', 'assessments.items', 'attachments']);
    }

    public function createReviewRound(ServiceDeficiencyMemo $memo): void
    {
        $round = ((int) $memo->workflowSteps()->max('round')) + 1;
        $sequence = (int) $memo->workflowSteps()->max('sequence');
        $stages = [WorkflowStepType::CmcAssessment, WorkflowStepType::AmCmcToDgm, WorkflowStepType::DgmApproval];
        $created = collect();

        foreach ($stages as $stage) {
            $prior = $memo->workflowSteps()->where('stage', $stage->value)->reorder('sequence', 'desc')->firstOrFail();
            $assignee = $stage === WorkflowStepType::CmcAssessment
                ? ($memo->cmcStaff()->first() ?? $prior->assignee()->firstOrFail())
                : $prior->assignee()->firstOrFail();
            $created->push($memo->workflowSteps()->create([
                'round' => $round,
                'sequence' => ++$sequence,
                'stage' => $stage->value,
                'role' => $stage === WorkflowStepType::CmcAssessment ? $prior->role : $stage->role()->value,
                'assigned_to' => $assignee->id,
                'assigned_name' => $assignee->name,
                'assigned_employee_number' => $assignee->employee_number,
                'decision' => 'pending',
            ]));
        }

        $memo->update([
            'status' => MemoStatus::CmcScrutiny->value,
            'current_workflow_step_id' => $created->first()->id,
        ]);
    }

    public function reassignCmcStaff(ServiceDeficiencyMemo $memo, User $actor, int $cmcStaffId): ServiceDeficiencyMemo
    {
        return DB::transaction(function () use ($memo, $actor, $cmcStaffId): ServiceDeficiencyMemo {
            $memo = ServiceDeficiencyMemo::query()->lockForUpdate()->findOrFail($memo->id);
            if ($memo->cmc_staff_id === null || in_array($memo->status, [MemoStatus::Closed, MemoStatus::Cancelled], true)) {
                abort(403);
            }

            $cmcStaff = $this->resolveCmcStaff($cmcStaffId);
            if ($memo->cmc_staff_id === $cmcStaff->id) {
                throw ValidationException::withMessages(['cmc_staff_id' => 'Select a different CMC Staff user for reassignment.']);
            }

            $this->applyCmcStaffAssignment($memo, $actor, $cmcStaff, true);

            return $memo->refresh()->load(['cmcStaff', 'currentWorkflowStep', 'workflowSteps.assignee']);
        });
    }

    private function statusFor(WorkflowStepType $stage): MemoStatus
    {
        return match ($stage) {
            WorkflowStepType::StationManager => MemoStatus::AwaitingStationManager,
            WorkflowStepType::LineReview => MemoStatus::LineReview,
            WorkflowStepType::AmCmcToScrutiny, WorkflowStepType::AmCmcToDgm => MemoStatus::AmCmcReview,
            WorkflowStepType::CmcAssignment => MemoStatus::CmcAssignment,
            WorkflowStepType::CmcAssessment => MemoStatus::CmcScrutiny,
            WorkflowStepType::DgmApproval => MemoStatus::DgmApproval,
        };
    }

    private function resolveCmcStaff(int $cmcStaffId): User
    {
        $cmcStaff = $this->users->enabledForRole(UserRole::CmcStaff->value)->firstWhere('id', $cmcStaffId);
        if (! $cmcStaff) {
            throw ValidationException::withMessages(['cmc_staff_id' => 'Select an enabled CMC Staff user.']);
        }

        return $cmcStaff;
    }

    private function cancellationRequestRemarks(?string $remarks): string
    {
        $requestMessage = 'Cancellation requested by Station Manager.';

        return filled($remarks)
            ? $requestMessage."\n\n".trim($remarks)
            : $requestMessage;
    }

    private function resolveLineManager(string $employeeNumber): User
    {
        $lineManager = $this->users->findEnabledForRoleByEmployeeNumber(UserRole::AssistantManager->value, $employeeNumber);
        if (! $lineManager) {
            throw ValidationException::withMessages([
                'forward_to_employee_number' => 'Enter the employee number of an enabled AM/Line user.',
            ]);
        }

        return $lineManager;
    }

    private function assignNextLegacyApproval(ServiceDeficiencyMemo $memo, WorkflowStage $stage, string $employeeNumber): void
    {
        $lineManager = $this->resolveLineManager($employeeNumber);
        $memo->approvals()->where('role', $stage->next()->value)->where('decision', 'pending')->update([
            'assigned_to' => $lineManager->id,
            'assigned_name' => $lineManager->name,
            'assigned_employee_number' => $lineManager->employee_number,
        ]);
    }

    private function assignNextVersionTwoStep(ServiceDeficiencyMemo $memo, MemoWorkflowStep $step, string $employeeNumber): void
    {
        $lineManager = $this->resolveLineManager($employeeNumber);
        $memo->workflowSteps()->where('sequence', '>', $step->sequence)->where('stage', WorkflowStepType::LineReview->value)->where('decision', 'pending')->update([
            'assigned_to' => $lineManager->id,
            'assigned_name' => $lineManager->name,
            'assigned_employee_number' => $lineManager->employee_number,
        ]);
    }

    private function applyCmcStaffAssignment(ServiceDeficiencyMemo $memo, User $actor, User $cmcStaff, bool $reassignment): void
    {
        $previousCmcStaffId = $memo->cmc_staff_id;
        $memo->update(['cmc_staff_id' => $cmcStaff->id]);
        $memo->workflowSteps()
            ->where('stage', WorkflowStepType::CmcAssessment->value)
            ->where('decision', 'pending')
            ->update([
                'assigned_to' => $cmcStaff->id,
                'assigned_name' => $cmcStaff->name,
                'assigned_employee_number' => $cmcStaff->employee_number,
            ]);

        $this->audit->record(
            $memo,
            $actor,
            $reassignment ? 'memo.cmc_staff.reassigned' : 'memo.cmc_staff.assigned',
            ['cmc_staff_id' => $previousCmcStaffId],
            ['cmc_staff_id' => $cmcStaff->id],
        );
    }

    private function assignCmcAssessmentToTeamLead(ServiceDeficiencyMemo $memo, MemoWorkflowStep $step, User $actor): void
    {
        abort_unless(
            $actor->is_enabled
                && $actor->hasRole(UserRole::CmcTeamLead->value)
                && $actor->can(UserPermission::ActOnRoutedMemos->value),
            403,
        );

        $assessmentStep = $memo->workflowSteps()
            ->where('sequence', '>', $step->sequence)
            ->where('stage', WorkflowStepType::CmcAssessment->value)
            ->where('decision', 'pending')
            ->lockForUpdate()
            ->firstOrFail();

        $assessmentStep->update([
            'role' => UserRole::CmcTeamLead->value,
            'assigned_to' => $actor->id,
            'assigned_name' => $actor->name,
            'assigned_employee_number' => $actor->employee_number,
        ]);
    }

    private function requiresAdministrativeReview(ServiceDeficiencyMemo $memo, MemoAssessment $current): bool
    {
        if (! $current->cap_warning || ! $current->bill_month) {
            return false;
        }

        $months = MemoAssessment::query()
            ->where('status', 'approved')
            ->where('cap_warning', true)
            ->whereHas('memo.serviceContract', fn ($query) => $query->where('contract_group', $memo->serviceContract()->value('contract_group')))
            ->pluck('bill_month')
            ->push($current->bill_month)
            ->map(fn ($month) => Carbon::parse($month)->startOfMonth())
            ->unique(fn ($month) => $month->format('Y-m'));

        return $months->contains(fn ($month) => $month->equalTo($current->bill_month->copy()->subMonth()))
            && $months->contains(fn ($month) => $month->equalTo($current->bill_month->copy()->subMonths(2)));
    }
}
