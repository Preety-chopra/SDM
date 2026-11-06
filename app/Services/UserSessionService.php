<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UserSessionService
{
    public const FINGERPRINT_KEY = 'auth.login_fingerprint';

    private const TOKEN_PREFIX = 'login:';

    public function __construct(
        private UserRepositoryInterface $users,
        private AuditService $audit,
    ) {}

    public function start(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            // Rotating Laravel's remember token revokes older remember cookies as well.
            if (! $this->users->setLoginToken($user, self::TOKEN_PREFIX.Str::random(60))) {
                return false;
            }

            $this->audit->record($user, $user, 'auth.other_devices_revoked');

            return true;
        });
    }

    public function end(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->users->clearLoginToken($user);
            $this->audit->record($user, $user, 'auth.logout');
        });
    }

    public function fingerprint(User $user): ?string
    {
        $token = $user->getRememberToken();

        return is_string($token) && str_starts_with($token, self::TOKEN_PREFIX) ? hash('sha256', $token) : null;
    }
}
