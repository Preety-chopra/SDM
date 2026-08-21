<?php

namespace App\Policies;

use App\Enums\MemoStatus;
use App\Enums\UserPermission;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;

class ServiceDeficiencyMemoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([UserPermission::ViewOwnMemos->value, UserPermission::ViewAllMemos->value, UserPermission::ViewRoutedMemos->value]);
    }

    public function create(User $user): bool
    {
        return $user->can(UserPermission::CreateMemos->value) && $user->station_id !== null;
    }

    public function view(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->can(UserPermission::ViewAllMemos->value)
            || ($user->can(UserPermission::ViewOwnMemos->value) && $memo->created_by === $user->id)
            || ($user->can(UserPermission::ViewRoutedMemos->value) && $memo->approvals()->where('assigned_to', $user->id)->where(function ($query) use ($memo): void {
                $query->where('decision', '!=', 'pending')->orWhere('role', $memo->current_stage?->value);
            })->exists());
    }

    public function act(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->is_enabled
            && $user->can(UserPermission::ActOnRoutedMemos->value)
            && in_array($memo->status, [MemoStatus::Submitted, MemoStatus::UnderReview], true)
            && $memo->approvals()->where('role', $memo->current_stage?->value)->where('assigned_to', $user->id)->where('decision', 'pending')->exists();
    }

    public function update(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->can(UserPermission::UpdateOwnDraftMemos->value)
            && $memo->created_by === $user->id
            && $memo->status === MemoStatus::Draft;
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
}
