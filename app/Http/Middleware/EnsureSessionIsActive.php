<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\UserSessionService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsActive
{
    public function __construct(private UserSessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();
        $session = $request->session();

        if (! $user instanceof User || ! $session->has($guard->getName())) {
            return $next($request);
        }

        $now = now()->getTimestamp();
        $lastActivity = $session->get(UserSessionService::LAST_ACTIVITY_KEY);
        $expired = is_int($lastActivity) && $now - $lastActivity >= UserSessionService::INACTIVITY_TIMEOUT_SECONDS;

        if ($guard->viaRemember() || ! is_int($lastActivity) || $expired) {
            $this->sessions->end($user, $expired ? 'inactivity' : 'fresh_login_required');
            $guard->logoutCurrentDevice();
            $session->invalidate();
            $session->regenerateToken();

            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        $session->put(UserSessionService::LAST_ACTIVITY_KEY, $now);

        return $next($request);
    }
}
