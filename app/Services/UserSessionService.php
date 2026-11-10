<?php

namespace App\Services;

use App\Models\User;

final class UserSessionService
{
    public const LAST_ACTIVITY_KEY = 'auth.last_activity_at';

    public const INACTIVITY_TIMEOUT_SECONDS = 15 * 60;

    public function __construct(private AuditService $audit) {}

    public function end(User $user, ?string $reason = null): void
    {
        $this->audit->record($user, $user, 'auth.logout', after: $reason === null ? [] : ['reason' => $reason]);
    }
}
