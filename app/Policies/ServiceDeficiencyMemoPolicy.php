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
        return $user->canAny([UserPermission::ViewOwnMemos->value, UserPermission::ViewAllMemos->value]);
    }

    public function create(User $user): bool
    {
        return $user->can(UserPermission::CreateMemos->value) && $user->station_id !== null;
    }

    public function view(User $user, ServiceDeficiencyMemo $memo): bool
    {
        return $user->can(UserPermission::ViewAllMemos->value)
            || ($user->can(UserPermission::ViewOwnMemos->value) && $memo->created_by === $user->id);
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
}
