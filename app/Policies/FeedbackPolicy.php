<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FeedbackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_enabled && $user->hasRole(UserRole::Administrator->value);
    }

    public function create(User $user): bool
    {
        return $user->is_enabled;
    }

    public function reply(User $user, Feedback $feedback): bool
    {
        return $this->viewAny($user) && $feedback->user_id !== null;
    }

    public function viewOwn(User $user, Feedback $feedback): Response
    {
        return $user->is_enabled && $feedback->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
