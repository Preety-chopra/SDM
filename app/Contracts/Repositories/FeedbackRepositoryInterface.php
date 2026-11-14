<?php

namespace App\Contracts\Repositories;

use App\Models\Feedback;
use App\Models\FeedbackReply;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FeedbackRepositoryInterface
{
    public function create(array $data): Feedback;

    public function paginate(): LengthAwarePaginator;

    public function latestId(): int;

    public function countAfter(int $id): int;

    public function loadConversation(Feedback $feedback): Feedback;

    public function findForUpdate(int $id): Feedback;

    public function createReply(Feedback $feedback, User $author, string $message, bool $fromSender): FeedbackReply;

    public function hasAdministratorReply(Feedback $feedback): bool;

    /** @param list<int> $replyIds */
    public function markFollowUpsRead(Feedback $feedback, array $replyIds): void;

    public function paginateForSender(User $sender): LengthAwarePaginator;

    public function unreadReplyCount(User $sender): int;

    /** @param list<int> $replyIds */
    public function markRepliesRead(User $sender, array $replyIds): void;
}
