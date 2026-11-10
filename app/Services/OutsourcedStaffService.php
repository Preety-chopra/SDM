<?php

namespace App\Services;

use App\Contracts\Repositories\OutsourcedStaffRepositoryInterface;
use App\Models\OutsourcedStaff;
use App\Models\User;
use App\Support\OutsourcedStaffIdentifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OutsourcedStaffService
{
    public function __construct(private OutsourcedStaffRepositoryInterface $staff, private AuditService $audit) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->staff->paginate($filters);
    }

    public function lookupForMemo(string $identifier, int $contractorId): array
    {
        $matches = $this->staff->matchingIdentifier(trim($identifier), OutsourcedStaffIdentifier::hash(OutsourcedStaffIdentifier::aadhaar($identifier)), $contractorId);
        if ($matches->count() > 1) {
            return ['status' => 'ambiguous'];
        }
        $staff = $matches->first();

        return $staff === null ? ['status' => 'missing'] : [
            'status' => 'found', 'employee_number' => $staff->employee_id, 'staff_name' => $staff->name,
        ];
    }

    public function resolveMemoStaff(array $data): array
    {
        $identifier = $data['employee_number'] ?? null;
        if (! is_string($identifier) || $identifier === '') {
            return $data;
        }
        $result = empty($data['contractor_id']) ? ['status' => 'missing'] : $this->lookupForMemo($identifier, (int) $data['contractor_id']);
        if ($result['status'] === 'ambiguous') {
            throw ValidationException::withMessages(['employee_number' => 'More than one staff member matches this number. Enter the EMP ID instead.']);
        }
        if ($result['status'] === 'found') {
            $data['employee_number'] = $result['employee_number'];
            $data['staff_name'] = $result['staff_name'];
        } else {
            $data['employee_number'] = OutsourcedStaffIdentifier::mask($identifier);
        }

        return $data;
    }

    public function save(?OutsourcedStaff $staff, array $attributes, User $actor): OutsourcedStaff
    {
        return DB::transaction(function () use ($staff, $attributes, $actor): OutsourcedStaff {
            // Audit operational changes without copying identity documents or personal details into logs.
            $fields = ['employee_id', 'contractor_id', 'designation', 'verification_status', 'is_active'];
            $before = $staff?->only($fields) ?? [];
            $event = $staff === null ? 'outsourced_staff.created' : 'outsourced_staff.updated';
            $clearAadhaar = (bool) ($attributes['clear_aadhaar'] ?? false);
            unset($attributes['clear_aadhaar']);
            if ($clearAadhaar) {
                $attributes['aadhaar_number'] = null;
            } elseif (empty($attributes['aadhaar_number'])) {
                unset($attributes['aadhaar_number']);
            }
            $staff = $this->staff->save($staff, $attributes);
            $after = [...$staff->only($fields), 'changed_fields' => array_keys($staff->getChanges())];
            $this->audit->record($staff, $actor, $event, $before, $after);

            return $staff;
        });
    }
}
