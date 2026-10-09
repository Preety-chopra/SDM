<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
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
            return $steps->filter(fn (MemoWorkflowStep $step): bool => in_array($step->stage, [
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
        ])) {
            return $this->throughNextPendingStep($steps);
        }

        return $steps;
    }

    /**
     * @param  Collection<int, MemoWorkflowStep>  $steps
     * @return Collection<int, MemoWorkflowStep>
     */
    private function throughNextPendingStep(Collection $steps): Collection
    {
        $lastCompletedStep = $steps->last(
            fn (MemoWorkflowStep $step): bool => $step->decision !== 'pending',
        );
        $nextPendingStep = $steps->first(
            fn (MemoWorkflowStep $step): bool => $lastCompletedStep === null
                || $step->sequence > $lastCompletedStep->sequence,
        );
        $lastVisibleSequence = $nextPendingStep?->sequence ?? $lastCompletedStep?->sequence;

        return $lastVisibleSequence === null
            ? collect()
            : $this->throughSequence($steps, $lastVisibleSequence);
    }

    /**
     * @param  Collection<int, MemoWorkflowStep>  $steps
     * @return Collection<int, MemoWorkflowStep>
     */
    private function throughSequence(Collection $steps, int $sequence): Collection
    {
        return $steps->takeWhile(
            fn (MemoWorkflowStep $step): bool => $step->sequence <= $sequence,
        )->values();
    }
}
