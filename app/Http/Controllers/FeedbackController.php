<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function __construct(private FeedbackService $feedback) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Feedback::class);

        return view('feedback.index', ['feedback' => $this->feedback->listing()]);
    }

    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $this->feedback->submit($request->validated(), $request->user());

        return response()->json(['message' => 'Thank you. Your feedback has been submitted.'], 201);
    }
}
