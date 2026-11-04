<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\FeedbackRepositoryInterface;
use App\Models\Feedback;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
            ->latest()
            ->latest('id')
            ->paginate(25);
    }
}
