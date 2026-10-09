<?php

namespace App\Policies;

use App\Enums\MemoStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;

class ServiceDeficiencyMemoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(UserPermission::CreateMemos->value);
    }

    public function bulkForward(User $user): bool
    {
        return $user->is_enabled
            && $user->hasRole(UserRole::ManagerOperations->value)
            && $user->can(UserPermission::ActOnRoutedMemos->value);
    }

    public function view(User $user, ServiceDeficiencyMemo $memo): bool
    {
        if ($memo->status === MemoStatus::Draft) {
            return $memo->created_by === $user->id;
        }

        if ($user->isStationController()) {
            return $user->can(UserPermission::ViewOwnMemos->value)
                && $memo->created_by === $user->id;
        }

        return true;
    }

    public function act(User $user, ServiceDeficiencyMemo $memo): bool
    {
        if ($memo->workflow_version === 2) {
            $currentStep = $memo->currentWorkflowStep;

            return $user->is_enabled
                && $user->can(UserPermission::ActOnRoutedMemos->value)
                && $currentStep?->decision === 'pending'
                && ($currentStep->assigned_to === $user->id
                    || ($currentStep->stage === WorkflowStepType::CmcAssessment
                        && $user->hasRole(UserRole::CmcTeamLead->value)));
        }

        return $user->is_enabled
            && $user->can(UserPermission::ActOnRoutedMemos->value)
            && in_array($memo->status, [MemoStatus::Submitted, MemoStatus::UnderReview], true)
            && $memo->approvals()->where('role', $memo->current_stage?->value)->where('assigned_to', $user->id)->where('decision', 'pending')->exists();
    }

    public function update(User $user, ServiceDeficiencyMemo $memo): bool
    {
        if ($user->is_enabled
            && $user->hasRole(UserRole::StationManager->value)
            && $user->can(UserPermission::ActOnRoutedMemos->value)
            && $this->isPendingWithStationManager($memo, $user)) {
            return true;
        }

        return $user->can(UserPermission::UpdateOwnDraftMemos->value)
            && $memo->created_by === $user->id
            && ($memo->status === MemoStatus::Draft
                || ($user->station_id !== null && $this->isEditableSubmittedMemo($memo)));
    }

    public function submit(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->can(UserPermission::SubmitOwnDraftMemos->value)
            && $memo->created_by === $user->id
            && $memo->status === MemoStatus::Draft;
    }

    public function delete(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->can(UserPermission::UpdateOwnDraftMemos->value)
            && $memo->created_by === $user->id
            && $memo->status === MemoStatus::Draft;
    }

    public function assignCmcStaff(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->is_enabled
            && $user->can(UserPermission::AssignCmcStaff->value)
            && $memo->workflow_version >= 2
            && $memo->cmc_staff_id !== null
            && ! in_array($memo->status, [MemoStatus::Closed, MemoStatus::Cancelled], true);
    }

    public function reassignStationManager(User $user, ServiceDeficiencyMemo $memo): bool
    {
        if (! $user->is_enabled) {
            return false;
        }

        if ($this->isPendingWithStationManager($memo, $user)) {
            return $user->hasRole(UserRole::StationManager->value)
                && $user->can(UserPermission::ActOnRoutedMemos->value);
        }

        return $user->can(UserPermission::UpdateOwnDraftMemos->value)
            && $user->can(UserPermission::SubmitOwnDraftMemos->value)
            && ! $user->hasRole(UserRole::StationManager->value)
            && ! $user->hasRole(UserRole::Administrator->value)
            && $user->station_id !== null
            && $memo->created_by === $user->id
            && $this->isEditableSubmittedMemo($memo);
    }

    public function handoverLineManager(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->is_enabled
            && $user->hasRole(UserRole::AssistantManager->value)
            && $user->can(UserPermission::ActOnRoutedMemos->value)
            && $this->isPendingWithLineManager($memo, $user);
    }

    public function manageCmcLifecycle(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->is_enabled
            && ($memo->cmc_staff_id === $user->id || $user->can(UserPermission::ViewAllMemos->value));
    }

    private function isEditableSubmittedMemo(ServiceDeficiencyMemo $memo): bool
    {
        if (! $memo->submitted_at || $memo->submitted_at->lt(now()->subDays(2))) {
            return false;
        }

        if ($memo->workflow_version >= 2) {
            return $memo->status === MemoStatus::AwaitingStationManager
                && $memo->currentWorkflowStep?->stage === WorkflowStepType::StationManager
                && $memo->currentWorkflowStep?->decision === 'pending';
        }

        return $memo->status === MemoStatus::Submitted
            && $memo->current_stage === WorkflowStage::StationManager
            && $memo->approvals()->where('role', WorkflowStage::StationManager->value)->where('decision', 'pending')->exists();
    }

    private function isPendingWithStationManager(ServiceDeficiencyMemo $memo, User $user): bool
    {
        if ($memo->workflow_version >= 2) {
            return $memo->status === MemoStatus::AwaitingStationManager
                && $memo->currentWorkflowStep?->stage === WorkflowStepType::StationManager
                && $memo->currentWorkflowStep?->decision === 'pending'
                && $memo->currentWorkflowStep?->assigned_to === $user->id;
        }

        return $memo->status === MemoStatus::Submitted
            && $memo->current_stage === WorkflowStage::StationManager
            && $memo->approvals()
                ->where('role', WorkflowStage::StationManager->value)
                ->where('assigned_to', $user->id)
                ->where('decision', 'pending')
                ->exists();
    }

    private function isPendingWithLineManager(ServiceDeficiencyMemo $memo, User $user): bool
    {
        if ($memo->workflow_version >= 2) {
            return $memo->status === MemoStatus::LineReview
                && $memo->currentWorkflowStep?->stage === WorkflowStepType::LineReview
                && $memo->currentWorkflowStep?->decision === 'pending'
                && $memo->currentWorkflowStep?->assigned_to === $user->id;
        }

        return $memo->status === MemoStatus::UnderReview
            && $memo->current_stage === WorkflowStage::LineManager
            && $memo->approvals()
                ->where('role', WorkflowStage::LineManager->value)
                ->where('assigned_to', $user->id)
                ->where('decision', 'pending')
                ->exists();
    }
}
