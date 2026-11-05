<?php

namespace App\Contracts\Repositories;

use App\Models\Feedback;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FeedbackRepositoryInterface
{
    public function create(array $data): Feedback;

    public function paginate(): LengthAwarePaginator;

    public function latestId(): int;

    public function countAfter(int $id): int;
}
