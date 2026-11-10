<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\MemoApproval;
use App\Models\MemoWorkflowStep;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Support\Collection;

final class MemoTimelineService
{
    /** @return Collection<int, MemoWorkflowStep> */
    public function visibleSteps(ServiceDeficiencyMemo $memo, User $viewer): Collection
    {
        $steps = $memo->workflowSteps->sortBy('sequence')->values();
        $cancelledStep = $steps->first(
            fn (MemoWorkflowStep $step): bool => $step->decision === 'cancelled',
        );

        if ($cancelledStep !== null) {
            return $this->throughSequence($steps, $cancelledStep->sequence);
        }

        if ($viewer->hasRole(UserRole::Administrator->value)) {
            return $steps;
        }

        if ($viewer->isStationController()) {
            return $steps->filter(
                fn (MemoWorkflowStep $step): bool => $step->stage === WorkflowStepType::StationManager,
            )->values();
        }

        if ($viewer->hasRole(UserRole::StationManager->value)) {
            return $this->throughNextPendingStep($steps)->filter(fn (MemoWorkflowStep $step): bool => in_array($step->stage, [
                WorkflowStepType::StationManager,
                WorkflowStepType::LineReview,
            ], true))->values();
        }

        if ($viewer->hasAnyRole([
            UserRole::AssistantManager->value,
            UserRole::ManagerOperations->value,
            UserRole::CmcTeamLead->value,
            UserRole::CmcStaff->value,
            UserRole::DeputyGeneralManager->value,
            UserRole::Aft->value,
            UserRole::DgmAft->value,
        ])) {
            return $this->throughNextPendingStep($steps);
        }

        return $steps;
    }

    /** @return Collection<int, MemoApproval> */
    public function visibleApprovals(ServiceDeficiencyMemo $memo, User $viewer): Collection
    {
        $approvals = $memo->approvals->sortBy('sequence')->values();

        if ($viewer->isStationController()) {
            return $approvals->filter(fn (MemoApproval $approval): bool => $approval->role === WorkflowStage::StationManager)->values();
        }

        if ($viewer->hasRole(UserRole::StationManager->value)) {
            return $this->throughNextPendingStep($approvals)->filter(fn (MemoApproval $approval): bool => in_array($approval->role, [
                WorkflowStage::StationManager,
                WorkflowStage::LineManager,
            ], true))->values();
        }

        return $approvals;
    }

    /**
     * @param  Collection<int, MemoWorkflowStep|MemoApproval>  $steps
     * @return Collection<int, MemoWorkflowStep|MemoApproval>
     */
    private function throughNextPendingStep(Collection $steps): Collection
    {
        $lastCompletedStep = $steps->last(
            fn (MemoWorkflowStep|MemoApproval $step): bool => $step->decision !== 'pending',
        );
        $nextPendingStep = $steps->first(
            fn (MemoWorkflowStep|MemoApproval $step): bool => $lastCompletedStep === null
                || $step->sequence > $lastCompletedStep->sequence,
        );
        $lastVisibleSequence = $nextPendingStep?->sequence ?? $lastCompletedStep?->sequence;

        return $lastVisibleSequence === null
            ? collect()
            : $this->throughSequence($steps, $lastVisibleSequence);
    }

    /**
     * @param  Collection<int, MemoWorkflowStep|MemoApproval>  $steps
     * @return Collection<int, MemoWorkflowStep|MemoApproval>
     */
    private function throughSequence(Collection $steps, int $sequence): Collection
    {
        return $steps->takeWhile(
            fn (MemoWorkflowStep|MemoApproval $step): bool => $step->sequence <= $sequence,
        )->values();
    }
}
