<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Repositories\StationRepositoryInterface;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Station;
use App\Services\AuthenticationService;
use App\Services\FeedbackService;
use App\Services\ServiceDeficiencyMemoService;
use App\Services\UserSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private StationRepositoryInterface $stations,
        private ServiceDeficiencyMemoService $memos,
        private FeedbackService $feedback,
        private UserSessionService $sessions,
    ) {}

    /**
     * Display the login view.
     */
    public function create(): View
    {
        $lineOrder = array_flip([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 'AEL']);
        $stationOptions = $this->stations->active()
            ->sortBy(function (Station $station) use ($lineOrder): array {
                $lineCode = $station->metroLine?->code;

                return [
                    $lineOrder[$lineCode] ?? count($lineOrder),
                    $lineCode === null,
                    $lineCode,
                    $lineCode === null ? PHP_INT_MAX : ($station->sequence_no ?? PHP_INT_MAX),
                ];
            })
            ->map(function (Station $station): array {
                $lineCode = $station->metroLine?->code;

                return [
                    'id' => $station->id,
                    'label' => $lineCode === null ? $station->name : Str::start($lineCode, 'L-').' '.$station->name,
                ];
            });

        return view('auth.login', ['stationOptions' => $stationOptions]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, AuthenticationService $authentication): RedirectResponse
    {
        $request->authenticate($authentication);

        $request->session()->regenerate();

        if ($request->filled('station_id')) {
            $request->session()->put('auth.station_id', $request->integer('station_id'));
        } else {
            $request->session()->forget('auth.station_id');
        }

        if ($request->user()->hasAnyRole([UserRole::Administrator->value, UserRole::Station->value, 'station_controller'])) {
            $request->session()->forget('pending_sdm_summary');
        } else {
            $request->session()->flash('pending_sdm_summary', $this->memos->pendingActionSummary($request->user()));
        }

        $request->session()->forget('new_feedback_count');
        $newFeedbackCount = $this->feedback->newCount($request->user());

        if ($newFeedbackCount > 0) {
            $request->session()->flash('new_feedback_count', $newFeedbackCount);
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
        $this->sessions->end($request->user());
        Auth::guard('web')->logoutCurrentDevice();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
