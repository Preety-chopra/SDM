<?php

namespace App\Contracts\Repositories;

use App\Enums\WorkflowStage;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ServiceDeficiencyMemoRepositoryInterface
{
    public function paginateFor(User $user, ?WorkflowStage $pendingStage = null): LengthAwarePaginator;

    /** @return array<string, array{memos: Collection, total: int}> */
    public function pendingByStage(int $limit = 5): array;

    public function pendingActionCount(User $user): int;

    public function create(array $attributes): ServiceDeficiencyMemo;

    public function update(ServiceDeficiencyMemo $memo, array $attributes): ServiceDeficiencyMemo;

    public function forceDelete(ServiceDeficiencyMemo $memo): void;
}
