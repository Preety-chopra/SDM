<?php

namespace App\Contracts\Repositories;

use App\Models\OutsourcedStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface OutsourcedStaffRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function save(?OutsourcedStaff $staff, array $attributes): OutsourcedStaff;

    public function employeeIds(): array;

    public function matchingIdentifier(string $employeeId, ?string $aadhaarHash, int $contractorId): Collection;
}
