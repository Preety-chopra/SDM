<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Repositories\StationRepositoryInterface;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private StationRepositoryInterface $stations) {}

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login', ['stations' => $this->stations->active()]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->filled('station_id')) {
            $request->session()->put('auth.station_id', $request->integer('station_id'));
        } else {
            $request->session()->forget('auth.station_id');
        }

        $defaultDestination = $request->user()->hasAnyRole([UserRole::Station->value, 'station_controller'])
            ? route('sdm-memos.create', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($defaultDestination);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
