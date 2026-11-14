<?php

namespace App\Services;

use App\Contracts\Repositories\FeedbackRepositoryInterface;
use App\Contracts\Repositories\StationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\FeedbackReply;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class FeedbackService
{
    public function __construct(
        private FeedbackRepositoryInterface $feedback,
        private AuditService $audit,
        private UserRepositoryInterface $users,
        private StationRepositoryInterface $stations,
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
    public function submit(array $data, User $actor, ?int $loginStationId): Feedback
    {
        return DB::transaction(function () use ($data, $actor, $loginStationId): Feedback {
            $stationName = $loginStationId !== null && ($actor->isStationController() || $actor->hasRole(UserRole::StationManager->value))
                ? $this->stations->nameById($loginStationId)
                : null;

            $feedback = $this->feedback->create([
                'user_id' => $actor->id,
                'message' => $data['message'],
                'page_url' => $data['page_url'],
                'station_name' => $stationName,
            ]);

            $this->audit->record($feedback, $actor, 'feedback.submitted', after: [
                'user_id' => $actor->id,
                ...($stationName === null ? [] : ['station_name' => $stationName]),
            ]);

            return $feedback;
        });
    }

    public function conversation(Feedback $feedback): Feedback
    {
        $conversation = $this->feedback->loadConversation($feedback);
        $unreadIds = $this->unreadIds($conversation->replies, fromSender: true);

        if ($unreadIds !== []) {
            $this->feedback->markFollowUpsRead($conversation, $unreadIds);
        }

        return $conversation;
    }

    public function senderConversation(Feedback $feedback, User $sender): Feedback
    {
        $conversation = $this->feedback->loadConversation($feedback);
        $this->acknowledgeAdministratorReplies($sender, $conversation->replies);

        return $conversation;
    }

    public function reply(Feedback $feedback, User $actor, string $message): void
    {
        DB::transaction(function () use ($feedback, $actor, $message): void {
            $feedback = $this->feedback->findForUpdate($feedback->id);
            abort_if($feedback->user_id === null, 403);

            $reply = $this->feedback->createReply($feedback, $actor, $message, fromSender: false);
            $this->audit->record($feedback, $actor, 'feedback.replied', after: [
                'reply_id' => $reply->id,
                'recipient_id' => $feedback->user_id,
            ]);
        });
    }

    public function followUp(Feedback $feedback, User $sender, string $message): void
    {
        DB::transaction(function () use ($feedback, $sender, $message): void {
            $feedback = $this->feedback->findForUpdate($feedback->id);
            Gate::forUser($sender)->authorize('viewOwn', $feedback);
            abort_unless($this->feedback->hasAdministratorReply($feedback), 403);

            $reply = $this->feedback->createReply($feedback, $sender, $message, fromSender: true);
            $this->audit->record($feedback, $sender, 'feedback.followed_up', after: ['reply_id' => $reply->id]);
        });
    }

    public function senderInbox(User $sender): LengthAwarePaginator
    {
        $listing = $this->feedback->paginateForSender($sender);
        $this->acknowledgeAdministratorReplies($sender, $listing->getCollection()->flatMap(fn (Feedback $item) => $item->replies));

        return $listing;
    }

    public function unreadReplyCount(User $sender): int
    {
        return $this->feedback->unreadReplyCount($sender);
    }

    private function acknowledgeAdministratorReplies(User $sender, Collection $replies): void
    {
        $unreadIds = $this->unreadIds($replies, fromSender: false);

        if ($unreadIds !== []) {
            $this->feedback->markRepliesRead($sender, $unreadIds);
        }
    }

    /** @return list<int> */
    private function unreadIds(Collection $replies, bool $fromSender): array
    {
        return $replies->filter(fn (FeedbackReply $reply): bool => $reply->from_sender === $fromSender && $reply->read_at === null)
            ->pluck('id')->all();
    }
}
