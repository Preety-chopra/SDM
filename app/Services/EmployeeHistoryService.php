<?php

namespace App\Services;

use App\Contracts\Repositories\EmployeeHistoryRepositoryInterface;
use App\Models\ServiceDeficiencyMemo;

final class EmployeeHistoryService
{
    public function __construct(private EmployeeHistoryRepositoryInterface $history) {}

    public function search(array $filters, ?int $excludeMemoId = null): array
    {
        $hasIdentity = filled($filters['contractor_id'] ?? null) && filled($filters['employee_number'] ?? null);
        $memos = $hasIdentity ? $this->history->memos((int) $filters['contractor_id'], $filters['employee_number'], $excludeMemoId) : null;

        return [
            'filters' => $filters,
            'memos' => $memos,
            'assessments' => $memos?->getCollection()->mapWithKeys(fn (ServiceDeficiencyMemo $memo): array => [
                $memo->id => $memo->assessments->last(fn ($assessment): bool => $assessment->status === 'approved')
                    ?? $memo->assessments->last(),
            ]) ?? collect(),
            'historicalRecords' => $hasIdentity ? $this->history->historicalRecords((int) $filters['contractor_id'], $filters['employee_number']) : null,
        ];
    }

    public function forMemo(ServiceDeficiencyMemo $memo): array
    {
        return $this->search([
            'contractor_id' => $memo->contractor_id,
            'employee_number' => $memo->employee_number,
        ], $memo->id);
    }
}
