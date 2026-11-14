<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\FeedbackRepositoryInterface;
use App\Models\Feedback;
use App\Models\FeedbackReply;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackRepository implements FeedbackRepositoryInterface
{
    public function create(array $data): Feedback
    {
        return Feedback::query()->create($data);
    }

    public function paginate(): LengthAwarePaginator
    {
        return Feedback::query()
            ->with('user:id,name,employee_number')
            ->withCount('replies')
            ->withExists(['replies as has_unread_follow_ups' => fn (Builder $query) => $query->where('from_sender', true)->whereNull('read_at')])
            ->latest()
            ->latest('id')
            ->paginate(25);
    }

    public function latestId(): int
    {
        return (int) Feedback::query()->max('id');
    }

    public function countAfter(int $id): int
    {
        return Feedback::query()->where('id', '>', $id)->count();
    }

    public function loadConversation(Feedback $feedback): Feedback
    {
        return $feedback->load('user:id,name,employee_number', 'replies.author:id,name');
    }

    public function findForUpdate(int $id): Feedback
    {
        return Feedback::query()->lockForUpdate()->findOrFail($id);
    }

    public function createReply(Feedback $feedback, User $author, string $message, bool $fromSender): FeedbackReply
    {
        return $feedback->replies()->create([
            'author_id' => $author->id,
            'message' => $message,
            'from_sender' => $fromSender,
        ]);
    }

    public function hasAdministratorReply(Feedback $feedback): bool
    {
        return $feedback->replies()->where('from_sender', false)->exists();
    }

    public function markFollowUpsRead(Feedback $feedback, array $replyIds): void
    {
        FeedbackReply::query()->whereBelongsTo($feedback)
            ->where('from_sender', true)->whereNull('read_at')->whereKey($replyIds)
            ->update(['read_at' => now()]);
    }

    public function paginateForSender(User $sender): LengthAwarePaginator
    {
        return Feedback::query()
            ->whereBelongsTo($sender, 'user')
            ->with('replies')
            ->withMax('replies', 'id')
            ->orderByDesc('replies_max_id')
            ->latest('id')
            ->paginate(25);
    }

    public function unreadReplyCount(User $sender): int
    {
        return $this->unreadRepliesForSender($sender)->count();
    }

    public function markRepliesRead(User $sender, array $replyIds): void
    {
        $this->unreadRepliesForSender($sender)->whereKey($replyIds)->update(['read_at' => now()]);
    }

    /** @return Builder<FeedbackReply> */
    private function unreadRepliesForSender(User $sender): Builder
    {
        return FeedbackReply::query()
            ->where('from_sender', false)
            ->whereNull('read_at')
            ->whereHas('feedback', fn (Builder $query) => $query->whereBelongsTo($sender, 'user'));
    }
}
