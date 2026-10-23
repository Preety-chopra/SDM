<?php

namespace App\Contracts\Repositories;

use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ServiceDeficiencyMemoRepositoryInterface
{
    /** @param array<string, int|string> $filters */
    public function paginateFor(User $user, array $filters = [], ?WorkflowStage $pendingStage = null, ?string $category = null): LengthAwarePaginator;

    /**
     * @param  array<string, int|string>  $filters
     * @param  list<int>  $memoIds
     * @return Collection<int, ServiceDeficiencyMemo>
     */
    public function bulkForwardableFor(User $user, WorkflowStepType $stage, array $filters = [], array $memoIds = []): Collection;

    /**
     * @param  list<int>  $memoIds
     * @return Collection<int, ServiceDeficiencyMemo>
     */
    public function bulkCmcActionableFor(User $user, WorkflowStepType $stage, array $memoIds): Collection;

    /** @return Collection<int, ServiceDeficiencyMemo> */
    public function recentSubmittedBy(User $user, int $limit = 10): Collection;

    public function paginateDraftsBy(User $user): LengthAwarePaginator;

    /** @return array<string, array{memos: Collection, total: int}> */
    public function pendingByStage(int $limit = 5): array;

    /** @return array<string, array{memos: Collection, total: int}> */
    public function pendingByWorkflowStep(int $limit = 5): array;

    public function pendingActionCount(User $user): int;

    /** @return Collection<int, ServiceDeficiencyMemo> */
    public function urgentPendingFor(User $user, int $limit = 10): Collection;

    public function nextSerialSequence(string $period): int;

    public function create(array $attributes): ServiceDeficiencyMemo;

    public function update(ServiceDeficiencyMemo $memo, array $attributes): ServiceDeficiencyMemo;

    public function forceDelete(ServiceDeficiencyMemo $memo): void;
}
