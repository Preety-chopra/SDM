<?php

namespace App\Services;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Contracts\Repositories\OutsourcedStaffRepositoryInterface;
use App\Support\OutsourcedStaffRules;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OutsourcedStaffSeedService
{
    public function __construct(
        private MasterDataRepositoryInterface $masterData,
        private OutsourcedStaffRepositoryInterface $staff,
        private AuditService $audit,
    ) {}

    public function seed(array $snapshot): array
    {
        Validator::make($snapshot, [
            'contractors' => ['required', 'array', 'min:1'],
            'staff' => ['required', 'array', 'min:1'],
        ])->validate();

        return DB::transaction(function () use ($snapshot): array {
            $contractors = $this->masterData->contractors()->keyBy(fn ($contractor): string => mb_strtolower($contractor->name));
            foreach ($snapshot['contractors'] as $row) {
                $attributes = Validator::make($row, [
                    'name' => ['required', 'string', 'max:255'],
                    'is_active' => ['required', 'boolean'],
                ])->validate();
                $key = mb_strtolower($attributes['name']);
                if ($contractors->has($key)) {
                    continue;
                }

                $contractor = $this->masterData->saveContractor(null, $attributes);
                $contractors->put($key, $contractor);
                $this->audit->record($contractor, null, 'contractor.created', after: $attributes);
            }

            $existing = array_fill_keys(array_map('mb_strtolower', $this->staff->employeeIds()), true);
            $seen = [];
            $rules = OutsourcedStaffRules::rules();
            unset($rules['contractor_id']);
            $result = ['created' => 0, 'skipped' => 0];
            foreach ($snapshot['staff'] as $row) {
                $attributes = Validator::make($row, [
                    ...$rules,
                    'contractor_name' => ['required', 'string', 'max:255'],
                ])->validate();
                $key = mb_strtolower($attributes['employee_id']);
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['employee_id' => 'The staff snapshot contains duplicate employee IDs.']);
                }
                $seen[$key] = true;
                if (isset($existing[$key])) {
                    $result['skipped']++;

                    continue;
                }

                $contractor = $contractors->get(mb_strtolower($attributes['contractor_name']));
                if ($contractor === null) {
                    throw ValidationException::withMessages(['contractor_name' => 'A staff contractor is missing from the snapshot and target database.']);
                }
                unset($attributes['contractor_name']);
                $attributes['contractor_id'] = $contractor->id;
                $member = $this->staff->save(null, $attributes);
                $this->audit->record($member, null, 'outsourced_staff.seeded', after: Arr::only($attributes, [
                    'employee_id', 'contractor_id', 'designation', 'verification_status', 'is_active',
                ]));
                $result['created']++;
            }

            return $result;
        });
    }
}
