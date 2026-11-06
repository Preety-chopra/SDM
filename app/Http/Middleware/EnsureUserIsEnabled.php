<?php

namespace App\Http\Middleware;

use App\Services\UserSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsEnabled
{
    public function __construct(private UserSessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_enabled === false) {
            $this->sessions->end($user);
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
