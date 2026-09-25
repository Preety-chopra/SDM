<?php

namespace App\Services;

use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\Contractor;
use App\Models\MemoAttachment;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ServiceDeficiencyMemoService
{
    public function __construct(
        private ServiceDeficiencyMemoRepositoryInterface $memos,
        private UserRepositoryInterface $users,
        private StationHierarchyService $hierarchies,
        private AuditService $audit,
    ) {}

    /** @param array<string, int|string> $filters */
    public function paginateFor(User $user, array $filters = [], ?WorkflowStage $pendingStage = null, ?string $category = null): LengthAwarePaginator
    {
        return $this->memos->paginateFor($user, $filters, $pendingStage, $category);
    }

    /** @return array<string, array{memos: Collection, total: int}> */
    public function pendingByStage(int $limit = 5): array
    {
        return $this->memos->pendingByStage($limit);
    }

    /** @return array<string, array{memos: Collection, total: int}> */
    public function pendingByWorkflowStep(int $limit = 5): array
    {
        return $this->memos->pendingByWorkflowStep($limit);
    }

    public function pendingActionCount(User $user): int
    {
        return $this->memos->pendingActionCount($user);
    }

    public function createDraft(User $user, array $data): ServiceDeficiencyMemo
    {
        return $this->persist(null, $user, $data, false);
    }

    public function createSubmitted(User $user, array $data): ServiceDeficiencyMemo
    {
        try {
            $this->resolveSubmissionAssignments(
                $user,
                $data,
                $this->submissionStationId($user, $data),
            );
        } catch (ValidationException $exception) {
            $this->createDraft($user, $data);
            throw $exception;
        }

        return $this->persist(null, $user, $data, true);
    }

    public function updateDraft(ServiceDeficiencyMemo $memo, User $user, array $data): ServiceDeficiencyMemo
    {
        return $this->persist($memo, $user, $data, false);
    }

    public function submit(ServiceDeficiencyMemo $memo, User $user, array $data): ServiceDeficiencyMemo
    {
        $this->resolveSubmissionAssignments(
            $user,
            $data,
            $this->submissionStationId($user, $data, $memo),
        );

        return $this->persist($memo, $user, $data, true);
    }

    public function deleteAttachment(MemoAttachment $attachment): void
    {
        DB::transaction(fn () => $attachment->delete());
        Storage::disk('local')->delete($attachment->storage_path);
    }

    public function deleteDraft(ServiceDeficiencyMemo $memo): void
    {
        DB::transaction(fn () => $this->memos->forceDelete($memo));
        Storage::disk('local')->deleteDirectory('sdm-memos/'.$memo->id);
    }

    private function persist(?ServiceDeficiencyMemo $memo, User $user, array $data, bool $submit): ServiceDeficiencyMemo
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($memo, $user, $data, $submit, &$storedPaths): ServiceDeficiencyMemo {
                $stationId = $this->submissionStationId($user, $data, $memo);
                $station = Station::query()->with('metroLine')->whereKey($stationId)->where('is_active', true)->firstOrFail();
                abort_unless($station->metroLine?->is_active, 422, 'Your station must be assigned to an active line.');
                $contractor = filled($data['contractor_id'] ?? null) ? Contractor::query()->whereKey($data['contractor_id'])->firstOrFail() : null;
                $contract = $this->resolveContract($data, $submit, $station->id);

                $attributes = [
                    'service_type' => $data['service_type'],
                    'serial_number' => $data['serial_number'] ?? null,
                    'station_id' => $station->id,
                    'station_name' => $station->name,
                    'line_name' => $station->metroLine->name,
                    'line_code' => $station->metroLine->code,
                    'contractor_id' => $contractor?->id,
                    'service_contract_id' => $contract?->id,
                    'contractor_name' => $contractor?->name,
                    'staff_name' => $data['staff_name'] ?? null,
                    'employee_number' => $data['employee_number'] ?? null,
                    'incident_date' => filled($data['incident_date'] ?? null) ? CarbonImmutable::createFromFormat('Y-m-d', $data['incident_date'])->toDateString() : null,
                    'incident_time' => $data['incident_time'] ?? null,
                    'updated_by' => $user->id,
                ];

                if ($submit) {
                    $attributes['status'] = $contract ? MemoStatus::AwaitingStationManager->value : MemoStatus::Submitted->value;
                    $attributes['workflow_version'] = $contract ? 2 : 1;
                    $attributes['current_stage'] = $contract ? null : WorkflowStage::StationManager->value;
                    $attributes['submitted_at'] = now();
                }

                if ($memo === null) {
                    $attributes['created_by'] = $user->id;
                    $memo = $this->memos->create($attributes);
                } else {
                    $memo = $this->memos->update($memo, $attributes);
                }

                $this->syncSections($memo, $data);
                $this->syncStaffAcknowledgement($memo, $data, $storedPaths);
                if ($submit && $contract && $memo->workflowSteps()->doesntExist()) {
                    $this->createWorkflowRoute($memo, $user, $contract, $data);
                } elseif ($submit && ! $contract && $memo->approvals()->doesntExist()) {
                    $this->createApprovalRoute($memo, $user, $data);
                }
                foreach (Arr::wrap($data['attachments'] ?? []) as $file) {
                    if ($file instanceof UploadedFile) {
                        $path = $file->store('sdm-memos/'.$memo->id, 'local');
                        $storedPaths[] = $path;
                        $memo->attachments()->create([
                            'original_name' => $file->getClientOriginalName(),
                            'storage_path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size_bytes' => $file->getSize(),
                            'uploaded_by' => $user->id,
                        ]);
                    }
                }

                $this->audit->record($memo, $user, $submit ? 'memo.submitted' : ($memo->wasRecentlyCreated ? 'memo.created' : 'memo.updated'), after: [
                    'status' => $memo->status->value,
                    'service_type' => $memo->service_type->value,
                    'contractor_id' => $memo->contractor_id,
                    'service_contract_id' => $memo->service_contract_id,
                    'station_id' => $memo->station_id,
                    'incident_time' => $memo->incident_time,
                ]);

                return $memo->load(['deficiencies.lateReporting', 'publicComplaint.station', 'cashCheck', 'attachments', 'staffAcknowledgement', 'currentWorkflowStep']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }

    private function createApprovalRoute(ServiceDeficiencyMemo $memo, User $creator, array $data): void
    {
        $assignees = $this->resolveSubmissionAssignments($creator, $data, $memo->station_id);

        foreach (WorkflowStage::cases() as $stage) {
            $assignee = $assignees[$stage->value];
            $attributes = [
                'role' => $stage->value,
                'sequence' => $stage->sequence(),
                'assigned_to' => $assignee->id,
                'assigned_name' => $assignee->name,
                'assigned_employee_number' => $assignee->employee_number,
                'decision' => 'pending',
            ];

            if ($stage === WorkflowStage::StationManager && $creator->hasRole(UserRole::StationManager->value)) {
                $attributes = [...$attributes,
                    'decision' => 'approved',
                    'acted_by' => $creator->id,
                    'signer_name' => $creator->name,
                    'signer_employee_number' => $creator->employee_number,
                    'acted_at' => now(),
                    'remarks' => 'Station Manager issued and signed the SDM.',
                ];
            }

            $memo->approvals()->create($attributes);
        }

        if ($creator->hasRole(UserRole::StationManager->value)) {
            $memo->update([
                'status' => MemoStatus::UnderReview->value,
                'current_stage' => WorkflowStage::LineManager->value,
            ]);
        }
    }

    public function reassignStationManager(ServiceDeficiencyMemo $memo, User $actor, string $employeeNumber): ServiceDeficiencyMemo
    {
        return DB::transaction(function () use ($memo, $actor, $employeeNumber): ServiceDeficiencyMemo {
            $lockedMemo = ServiceDeficiencyMemo::query()->whereKey($memo->id)->lockForUpdate()->firstOrFail();
            $stationManager = $this->users->findEnabledForRoleByEmployeeNumber(
                UserRole::StationManager->value,
                $employeeNumber,
            );

            if (! $stationManager) {
                throw ValidationException::withMessages([
                    'station_manager_employee_number' => 'Enter the employee number of an enabled Station Manager.',
                ]);
            }

            $assignment = $lockedMemo->workflow_version >= 2
                ? $lockedMemo->workflowSteps()
                    ->whereKey($lockedMemo->current_workflow_step_id)
                    ->where('stage', WorkflowStepType::StationManager->value)
                    ->where('decision', 'pending')
                    ->lockForUpdate()
                    ->first()
                : $lockedMemo->approvals()
                    ->where('role', WorkflowStage::StationManager->value)
                    ->where('decision', 'pending')
                    ->lockForUpdate()
                    ->first();

            $isAwaitingStationManager = $lockedMemo->workflow_version >= 2
                ? $lockedMemo->status === MemoStatus::AwaitingStationManager
                : $lockedMemo->status === MemoStatus::Submitted
                    && $lockedMemo->current_stage === WorkflowStage::StationManager;

            $isAssignedStationManager = $actor->is_enabled
                && $actor->hasRole(UserRole::StationManager->value)
                && $assignment?->assigned_to === $actor->id;

            if (! $lockedMemo->submitted_at
                || ! $isAwaitingStationManager
                || ! $assignment
                || (! $isAssignedStationManager && $lockedMemo->submitted_at->lt(now()->subDays(2)))) {
                throw ValidationException::withMessages([
                    'station_manager_employee_number' => 'The Station Manager can no longer be changed for this SDM.',
                ]);
            }

            if ($assignment->assigned_to === $stationManager->id) {
                throw ValidationException::withMessages([
                    'station_manager_employee_number' => 'Select a different Station Manager.',
                ]);
            }

            $before = [
                'station_manager_id' => $assignment->assigned_to,
                'station_manager_name' => $assignment->assigned_name,
                'station_manager_employee_number' => $assignment->assigned_employee_number,
            ];

            $assignment->update([
                'assigned_to' => $stationManager->id,
                'assigned_name' => $stationManager->name,
                'assigned_employee_number' => $stationManager->employee_number,
                'assignment_source' => $isAssignedStationManager ? 'handover' : 'workflow',
            ]);

            $this->audit->record($lockedMemo, $actor, $isAssignedStationManager
                ? 'memo.station_manager.handed_over'
                : 'memo.station_manager.reassigned', $before, [
                    'station_manager_id' => $stationManager->id,
                    'station_manager_name' => $stationManager->name,
                    'station_manager_employee_number' => $stationManager->employee_number,
                ]);

            return $lockedMemo->refresh();
        });
    }

    public function handoverLineManager(ServiceDeficiencyMemo $memo, User $actor, string $employeeNumber): ServiceDeficiencyMemo
    {
        return DB::transaction(function () use ($memo, $actor, $employeeNumber): ServiceDeficiencyMemo {
            $lockedMemo = ServiceDeficiencyMemo::query()->whereKey($memo->id)->lockForUpdate()->firstOrFail();
            $lineManager = $this->users->findEnabledForRoleByEmployeeNumber(
                UserRole::AssistantManager->value,
                $employeeNumber,
            );

            if (! $lineManager) {
                throw ValidationException::withMessages([
                    'line_manager_employee_number' => 'Enter the employee number of an enabled AM/Manager (Line).',
                ]);
            }

            $assignment = $lockedMemo->workflow_version >= 2
                ? $lockedMemo->workflowSteps()
                    ->whereKey($lockedMemo->current_workflow_step_id)
                    ->where('stage', WorkflowStepType::LineReview->value)
                    ->where('decision', 'pending')
                    ->lockForUpdate()
                    ->first()
                : $lockedMemo->approvals()
                    ->where('role', WorkflowStage::LineManager->value)
                    ->where('decision', 'pending')
                    ->lockForUpdate()
                    ->first();

            $isAwaitingLineManager = $lockedMemo->workflow_version >= 2
                ? $lockedMemo->status === MemoStatus::LineReview
                : $lockedMemo->status === MemoStatus::UnderReview
                    && $lockedMemo->current_stage === WorkflowStage::LineManager;

            $isAssignedLineManager = $actor->is_enabled
                && $actor->hasRole(UserRole::AssistantManager->value)
                && $assignment?->assigned_to === $actor->id;

            if (! $isAwaitingLineManager || ! $assignment || ! $isAssignedLineManager) {
                throw ValidationException::withMessages([
                    'line_manager_employee_number' => 'This SDM can no longer be handed over by this AM/Manager (Line).',
                ]);
            }

            if ($assignment->assigned_to === $lineManager->id) {
                throw ValidationException::withMessages([
                    'line_manager_employee_number' => 'Select a different AM/Manager (Line).',
                ]);
            }

            $before = [
                'line_manager_id' => $assignment->assigned_to,
                'line_manager_name' => $assignment->assigned_name,
                'line_manager_employee_number' => $assignment->assigned_employee_number,
            ];

            $assignment->update([
                'assigned_to' => $lineManager->id,
                'assigned_name' => $lineManager->name,
                'assigned_employee_number' => $lineManager->employee_number,
                'assignment_source' => 'handover',
            ]);

            $this->audit->record($lockedMemo, $actor, 'memo.line_manager.handed_over', $before, [
                'line_manager_id' => $lineManager->id,
                'line_manager_name' => $lineManager->name,
                'line_manager_employee_number' => $lineManager->employee_number,
            ]);

            return $lockedMemo->refresh();
        });
    }

    private function resolveContract(array $data, bool $submit, int $stationId): ?ServiceContract
    {
        if (! $submit || ! config('sdm.require_contract_workflow')) {
            return null;
        }

        $incidentDate = CarbonImmutable::createFromFormat('Y-m-d', $data['incident_date'])->toDateString();
        $contracts = ServiceContract::query()
            ->where('status', 'published')
            ->where('contractor_id', $data['contractor_id'])
            ->where('service_type', $data['service_type'])
            ->where(fn ($query) => $query->where('station_id', $stationId)->orWhereNull('station_id'))
            ->whereDate('effective_from', '<=', $incidentDate)
            ->whereDate('effective_to', '>=', $incidentDate)
            ->lockForUpdate()
            ->get();

        if ($contracts->count() !== 1) {
            throw ValidationException::withMessages([
                'contractor_id' => $contracts->isEmpty()
                    ? 'No active published contract matches this contractor, service type, and incident date.'
                    : 'More than one active contract matches these details. Ask an administrator to correct the contract dates.',
            ]);
        }

        return $contracts->first();
    }

    private function createWorkflowRoute(ServiceDeficiencyMemo $memo, User $creator, ServiceContract $contract, array $data): void
    {
        $legacyAssignments = $this->resolveSubmissionAssignments($creator, $data, $memo->station_id);
        $cmcTeamLeads = $this->users->enabledForRole(UserRole::CmcTeamLead->value);
        if ($cmcTeamLeads->count() !== 1) {
            throw ValidationException::withMessages([
                'contractor_id' => 'Exactly one enabled CMC Team Lead must be configured before an SDM can be submitted.',
            ]);
        }
        $cmcTeamLead = $cmcTeamLeads->sole();

        $assignments = [
            WorkflowStepType::StationManager->value => $legacyAssignments[WorkflowStage::StationManager->value],
            WorkflowStepType::LineReview->value => $legacyAssignments[WorkflowStage::LineManager->value],
            WorkflowStepType::AmCmcToScrutiny->value => $legacyAssignments[WorkflowStage::OperationsManager->value],
            WorkflowStepType::CmcAssignment->value => $cmcTeamLead,
            WorkflowStepType::CmcAssessment->value => null,
            WorkflowStepType::AmCmcToDgm->value => $legacyAssignments[WorkflowStage::OperationsManager->value],
            WorkflowStepType::DgmApproval->value => $legacyAssignments[WorkflowStage::DeputyGeneralManager->value],
        ];

        $steps = collect(WorkflowStepType::cases())->map(function (WorkflowStepType $stage, int $index) use ($memo, $assignments) {
            $assignee = $assignments[$stage->value];

            return $memo->workflowSteps()->create([
                'round' => 1,
                'sequence' => $index + 1,
                'stage' => $stage->value,
                'role' => $stage->role()->value,
                'assigned_to' => $assignee?->id,
                'assigned_name' => $assignee?->name ?? 'Awaiting CMC Team Lead assignment',
                'assigned_employee_number' => $assignee?->employee_number,
                'decision' => 'pending',
            ]);
        });

        $current = $steps->first();
        if ($creator->hasRole(UserRole::StationManager->value) && $current->assigned_to === $creator->id) {
            $current->update([
                'decision' => 'forwarded',
                'acted_by' => $creator->id,
                'actor_name' => $creator->name,
                'actor_employee_number' => $creator->employee_number,
                'acted_at' => now(),
                'remarks' => 'Station Manager issued and signed the SDM.',
            ]);
            $current = $steps->get(1);
            $memo->status = MemoStatus::LineReview;
        }

        $memo->current_workflow_step_id = $current->id;
        $memo->save();
    }

    private function syncStaffAcknowledgement(ServiceDeficiencyMemo $memo, array $data, array &$storedPaths): void
    {
        $file = $data['staff_signature'] ?? null;
        $mobile = $data['staff_mobile'] ?? null;
        if (! $file instanceof UploadedFile && blank($mobile)) {
            return;
        }

        $acknowledgement = $memo->staffAcknowledgement;
        if ($file instanceof UploadedFile) {
            $path = $file->store("sdm-memos/{$memo->id}/staff-acknowledgement", 'local');
            $storedPaths[] = $path;
            if ($acknowledgement?->proof_path) {
                Storage::disk('local')->delete($acknowledgement->proof_path);
            }
            $attributes = [
                'mobile_number' => $mobile,
                'method' => 'signature_upload',
                'status' => 'signed',
                'original_name' => $file->getClientOriginalName(),
                'proof_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'acknowledged_at' => now(),
            ];
            $memo->staffAcknowledgement()->updateOrCreate([], $attributes);
        } elseif ($acknowledgement && filled($mobile)) {
            $acknowledgement->update(['mobile_number' => $mobile]);
        }
    }

    private function submissionStationId(User $user, array $data, ?ServiceDeficiencyMemo $memo = null): int
    {
        return (int) ($data['station_id'] ?? $memo?->station_id ?? $user->station_id);
    }

    /** @return array<string, User> */
    private function resolveSubmissionAssignments(User $user, array $data, int $stationId): array
    {
        if ($user->hasRole(UserRole::StationManager->value)) {
            return $this->hierarchies->resolveForStationManagerSubmission(
                $user,
                $data['line_manager_employee_number'] ?? null,
            );
        }

        return $this->hierarchies->resolveForStation(
            $stationId,
            $data['station_manager_employee_number'] ?? null,
        );
    }

    private function syncSections(ServiceDeficiencyMemo $memo, array $data): void
    {
        $memo->deficiencies()->delete();
        foreach ($data['deficiencies'] ?? [] as $type) {
            $deficiency = $memo->deficiencies()->create([
                'type' => $type,
                'remarks' => $data['deficiency_remarks'][$type] ?? null,
            ]);
            if ($type === DeficiencyType::LateReporting->value && filled($data['late_reporting'] ?? null)) {
                $deficiency->lateReporting()->create($data['late_reporting']);
            }
        }

        $memo->publicComplaint()->delete();
        if (filled($data['public_complaint']['number'] ?? null)) {
            $complaintStation = Station::query()->findOrFail($data['public_complaint']['station_id']);
            $memo->publicComplaint()->create([
                'complaint_serial_number' => $data['public_complaint']['number'],
                'station_id' => $complaintStation->id,
                'station_name' => $complaintStation->name,
            ]);
        }

        $memo->cashCheck()->delete();
        $cashSelected = in_array(DeficiencyType::CashCheck->value, $data['deficiencies'] ?? [], true);
        if (SdmType::from($data['service_type']) === SdmType::Ticketing && $cashSelected) {
            $cash = Arr::except($data['cash_check'] ?? [], ['check_date', 'check_time']);
            $checkDate = $data['cash_check']['check_date'] ?? null;
            $checkTime = $data['cash_check']['check_time'] ?? null;
            $cash['checked_at'] = filled($checkDate) && filled($checkTime)
                ? CarbonImmutable::createFromFormat('Y-m-d H:i', "{$checkDate} {$checkTime}")
                : null;
            $memo->cashCheck()->create($cash);
        }
    }
}
