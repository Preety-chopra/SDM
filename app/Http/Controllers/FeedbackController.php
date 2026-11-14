<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackReplyRequest;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function __construct(private FeedbackService $feedback) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Feedback::class);

        $listing = $this->feedback->listing($request->user());
        $request->session()->forget('new_feedback_count');

        return view('feedback.index', ['feedback' => $listing]);
    }

    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $this->feedback->submit($request->validated(), $request->user(), $request->session()->get('auth.station_id'));

        return response()->json(['message' => 'Thank you. Your feedback has been submitted.'], 201);
    }

    public function show(Feedback $feedback): View
    {
        Gate::authorize('viewAny', Feedback::class);

        return view('feedback.show', ['feedback' => $this->feedback->conversation($feedback)]);
    }

    public function reply(StoreFeedbackReplyRequest $request, Feedback $feedback): RedirectResponse
    {
        $this->feedback->reply($feedback, $request->user(), $request->validated('reply'));

        return to_route('feedback.show', $feedback)->with('status', 'Reply sent.');
    }

    public function inbox(Request $request): View
    {
        return view('feedback.inbox', ['feedback' => $this->feedback->senderInbox($request->user())]);
    }

    public function thread(Request $request, Feedback $feedback): View
    {
        Gate::authorize('viewOwn', $feedback);

        return view('feedback.thread', ['feedback' => $this->feedback->senderConversation($feedback, $request->user())]);
    }

    public function followUp(StoreFeedbackReplyRequest $request, Feedback $feedback): RedirectResponse
    {
        $this->feedback->followUp($feedback, $request->user(), $request->validated('reply'));

        return to_route('feedback.thread', $feedback)->with('status', 'Reply sent.');
    }
}
