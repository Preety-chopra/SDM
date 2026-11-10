<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\OutsourcedStaffRepositoryInterface;
use App\Models\OutsourcedStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class OutsourcedStaffRepository implements OutsourcedStaffRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return OutsourcedStaff::query()
            ->with('contractor:id,name')
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('employee_id', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('entry_permit_number', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['contractor_id'] ?? null, fn (Builder $query, $id) => $query->where('contractor_id', $id))
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', $filters['is_active']))
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status))
            ->orderBy('employee_id')->orderBy('id')
            ->paginate(25)->withQueryString();
    }

    public function save(?OutsourcedStaff $staff, array $attributes): OutsourcedStaff
    {
        $staff ??= new OutsourcedStaff;
        $staff->fill($attributes)->save();

        return $staff;
    }

    public function employeeIds(): array
    {
        return OutsourcedStaff::query()->pluck('employee_id')->all();
    }

    public function matchingIdentifier(string $employeeId, ?string $aadhaarHash, int $contractorId): Collection
    {
        return OutsourcedStaff::query()->select(['id', 'employee_id', 'name'])
            ->where('contractor_id', $contractorId)
            ->where(function (Builder $query) use ($employeeId, $aadhaarHash): void {
                $query->where('employee_id', $employeeId);
                if ($aadhaarHash !== null) {
                    $query->orWhere('aadhaar_lookup_hash', $aadhaarHash);
                }
            })->limit(2)->get();
    }
}
