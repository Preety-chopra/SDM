<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
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
        $this->feedback->submit($request->validated(), $request->user());

        return response()->json(['message' => 'Thank you. Your feedback has been submitted.'], 201);
    }
}
