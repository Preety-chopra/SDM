<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\EmployeeHistoryRepositoryInterface;
use App\Enums\MemoStatus;
use App\Models\HistoricalSdm;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EmployeeHistoryRepository implements EmployeeHistoryRepositoryInterface
{
    public function memos(int $contractorId, string $employeeNumber, ?int $excludeMemoId = null, bool $highlightStaffProposal = false): LengthAwarePaginator
    {
        return ServiceDeficiencyMemo::query()
            ->select('service_deficiency_memos.*')
            ->where('contractor_id', $contractorId)
            ->where('employee_number', $employeeNumber)
            ->where('status', '!=', MemoStatus::Draft->value)
            ->when($excludeMemoId, fn (Builder $query) => $query->whereKeyNot($excludeMemoId))
            ->tap(CmcSdmTableQuery::forLiveMemos(...))
            ->when($highlightStaffProposal, CmcSdmTableQuery::withStaffProposal(...))
            ->orderByDesc('incident_date')->orderByDesc('id')
            ->paginate(20, ['*'], 'memos_page')->withQueryString();
    }

    public function historicalRecords(int $contractorId, string $employeeNumber): LengthAwarePaginator
    {
        return HistoricalSdm::query()
            ->where('contractor_id', $contractorId)
            ->where('employee_number', $employeeNumber)
            ->select([
                'id', 'memo_number', 'employee_name', 'employee_number', 'aadhaar_number', 'incident_date', 'source_date',
                'source_station', 'source_line', 'discrepancies', 'recommended_action',
                'contractor_penalty', 'negative_points', 'cp_number',
            ])
            ->tap(CmcSdmTableQuery::forImportedMemos(...))
            ->orderByDesc('incident_date')->orderByDesc('id')
            ->paginate(20, ['*'], 'historical_page')->withQueryString();
    }
}
