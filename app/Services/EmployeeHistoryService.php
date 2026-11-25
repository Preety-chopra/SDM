<?php

namespace App\Services;

use App\Contracts\Repositories\EmployeeHistoryRepositoryInterface;
use App\Models\ServiceDeficiencyMemo;

final class EmployeeHistoryService
{
    public function __construct(private EmployeeHistoryRepositoryInterface $history) {}

    public function search(array $filters, ?int $excludeMemoId = null, bool $highlightStaffProposal = false): array
    {
        $hasIdentity = filled($filters['contractor_id'] ?? null) && filled($filters['employee_number'] ?? null);

        return [
            'filters' => $filters,
            'memos' => $hasIdentity ? $this->history->memos((int) $filters['contractor_id'], $filters['employee_number'], $excludeMemoId, $highlightStaffProposal) : null,
            'historicalRecords' => $hasIdentity ? $this->history->historicalRecords((int) $filters['contractor_id'], $filters['employee_number']) : null,
        ];
    }

    public function forMemo(ServiceDeficiencyMemo $memo, bool $highlightStaffProposal = false): array
    {
        return $this->search([
            'contractor_id' => $memo->contractor_id,
            'employee_number' => $memo->employee_number,
        ], $memo->id, $highlightStaffProposal);
    }
}
