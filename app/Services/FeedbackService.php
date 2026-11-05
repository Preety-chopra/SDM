<?php

namespace App\Services;

use App\Contracts\Repositories\FeedbackRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class FeedbackService
{
    public function __construct(
        private FeedbackRepositoryInterface $feedback,
        private AuditService $audit,
        private UserRepositoryInterface $users,
    ) {}

    public function newCount(User $user): int
    {
        return $user->can('viewAny', Feedback::class)
            ? $this->feedback->countAfter((int) $user->feedback_last_seen_id)
            : 0;
    }

    public function listing(User $actor): LengthAwarePaginator
    {
        return DB::transaction(function () use ($actor): LengthAwarePaginator {
            // Capture the boundary before loading the list so later submissions remain new.
            $latestId = $this->feedback->latestId();
            $listing = $this->feedback->paginate();

            if ($listing->currentPage() === 1 && $this->users->acknowledgeFeedback($actor, $latestId)) {
                $this->audit->record($actor, $actor, 'feedback.notifications_acknowledged',
                    before: ['feedback_last_seen_id' => (int) $actor->feedback_last_seen_id],
                    after: ['feedback_last_seen_id' => $latestId],
                );
            }

            return $listing;
        });
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
