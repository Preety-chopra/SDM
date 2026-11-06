<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\UserSessionService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentLogin
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

        $fingerprint = $this->sessions->fingerprint($user);

        if ($fingerprint !== null && $guard->viaRemember()) {
            $session->put(UserSessionService::FINGERPRINT_KEY, $fingerprint);
        }

        $storedFingerprint = $session->get(UserSessionService::FINGERPRINT_KEY);

        if ($fingerprint === null || ! is_string($storedFingerprint) || ! hash_equals($fingerprint, $storedFingerprint)) {
            $guard->logoutCurrentDevice();
            $session->invalidate();
            $session->regenerateToken();

            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        return $next($request);
    }
}
