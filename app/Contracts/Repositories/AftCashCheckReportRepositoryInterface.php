<?php

namespace App\Contracts\Repositories;

use App\Models\AftCashCheckReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AftCashCheckReportRepositoryInterface
{
    public function paginateFor(User $user, bool $pending = false): LengthAwarePaginator;

    public function pendingCountFor(User $user): int;

    public function save(?AftCashCheckReport $report, array $attributes): AftCashCheckReport;

    public function findForUpdate(int $id): AftCashCheckReport;

    public function stationsForUpdate(array $ids): Collection;
}
