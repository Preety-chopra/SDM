<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeHistoryRepositoryInterface
{
    public function memos(int $contractorId, string $employeeNumber, ?int $excludeMemoId = null, bool $highlightStaffProposal = false): LengthAwarePaginator;

    public function historicalRecords(int $contractorId, string $employeeNumber): LengthAwarePaginator;
}
