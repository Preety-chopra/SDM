<?php

namespace App\Services;

use App\Contracts\Repositories\FeedbackRepositoryInterface;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class FeedbackService
{
    public function __construct(
        private FeedbackRepositoryInterface $feedback,
        private AuditService $audit,
    ) {}

    public function listing(): LengthAwarePaginator
    {
        return $this->feedback->paginate();
    }

    /** @param array{message: string, page_url: string} $data */
    public function submit(array $data, User $actor): Feedback
    {
        return DB::transaction(function () use ($data, $actor): Feedback {
            $feedback = $this->feedback->create([
                'user_id' => $actor->id,
                'message' => $data['message'],
                'page_url' => $data['page_url'],
            ]);
            $this->audit->record($feedback, $actor, 'feedback.submitted', after: ['user_id' => $actor->id]);

            return $feedback;
        });
    }
}
