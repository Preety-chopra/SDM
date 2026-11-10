<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\AftCashCheckReport;
use App\Models\User;

class AftCashCheckReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_enabled && ($this->create($user)
            || $user->can(UserPermission::ViewAllMemos->value)
            || ($user->can(UserPermission::ViewRoutedMemos->value) && $user->hasAnyRole([
                UserRole::DgmAft, UserRole::DeputyGeneralManager, UserRole::ManagerOperations, UserRole::CmcTeamLead, UserRole::CmcStaff,
            ])));
    }

    public function create(User $user): bool
    {
        return $user->is_enabled && $user->hasRole(UserRole::Aft) && $user->can(UserPermission::CreateMemos->value);
    }

    public function view(User $user, AftCashCheckReport $report): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->can(UserPermission::ViewAllMemos->value)
            || ($report->created_by === $user->id && $user->can(UserPermission::ViewOwnMemos->value))
            || ($report->status !== 'draft' && $user->can(UserPermission::ViewRoutedMemos->value)
                && $report->steps()->where('assigned_to', $user->id)->exists());
    }

    public function update(User $user, AftCashCheckReport $report): bool
    {
        return $this->create($user) && $user->can(UserPermission::UpdateOwnDraftMemos->value)
            && $report->created_by === $user->id && $report->status === 'draft' && $report->submitted_at === null;
    }

    public function submit(User $user, AftCashCheckReport $report): bool
    {
        return $this->update($user, $report) && $user->can(UserPermission::SubmitOwnDraftMemos->value);
    }

    public function act(User $user, AftCashCheckReport $report): bool
    {
        $step = $report->currentStep;

        return $user->is_enabled && $user->can(UserPermission::ActOnRoutedMemos->value)
            && in_array($report->status, ['submitted', 'under_review'], true)
            && $step?->decision === 'pending' && $step->assigned_to === $user->id
            && $user->hasRole($step->role)
            && ($step->stage !== WorkflowStepType::CmcAssessment || $user->can(UserPermission::AssessMemos->value));
    }
}
