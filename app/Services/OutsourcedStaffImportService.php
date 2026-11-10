<?php

namespace App\Services;

use App\Contracts\Repositories\OutsourcedStaffRepositoryInterface;
use App\Models\User;
use App\Support\OutsourcedStaffRules;
use App\Support\OutsourcedStaffWorkbookReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OutsourcedStaffImportService
{
    public function __construct(
        private OutsourcedStaffWorkbookReader $reader,
        private OutsourcedStaffRepositoryInterface $repository,
        private OutsourcedStaffService $staff,
    ) {}

    public function import(string $path, array $agencyMap, string $invalidAadhaar, User $actor, bool $dryRun = false): array
    {
        $rows = $this->reader->read($path);
        Validator::make(['agency_map' => $agencyMap, 'invalid_aadhaar' => $invalidAadhaar], [
            'agency_map' => ['required', 'array'],
            'agency_map.*' => ['required', 'integer', 'exists:contractors,id'],
            'invalid_aadhaar' => ['required', 'in:reject,blank'],
        ])->validate();

        $existing = array_fill_keys(array_map('mb_strtolower', $this->repository->employeeIds()), true);
        $seen = [];
        $prepared = [];
        $result = ['created' => 0, 'skipped' => 0, 'warnings' => []];
        $rules = OutsourcedStaffRules::rules();
        unset($rules['contractor_id']); // Every mapped contractor was validated once above.

        foreach ($rows as $row) {
            $number = $row['number'];
            [$employeeId, $name, $father, $day, $month, $year, $aadhaar, $category, $gender, $designation, $permit, $verification, $agency] = $row['values'];
            $key = mb_strtolower($employeeId);
            if (isset($seen[$key])) {
                $this->fail("Row {$number}: duplicate employee ID {$employeeId} in the workbook.");
            }
            $seen[$key] = true;
            if (isset($existing[$key])) {
                $result['skipped']++;

                continue;
            }
            if (! isset($agencyMap[$agency])) {
                $this->fail("Row {$number}: map contractor agency {$agency} before importing.");
            }
            if (! ctype_digit($day) || ! ctype_digit($month) || ! ctype_digit($year)
                || ! checkdate((int) $month, (int) $day, (int) $year)) {
                $this->fail("Row {$number}: invalid date of birth.");
            }
            if ($aadhaar !== '' && ! preg_match('/^[0-9]{12}$/', $aadhaar)) {
                if ($invalidAadhaar !== 'blank') {
                    $this->fail("Row {$number}: invalid Aadhaar. Correct it or choose to leave invalid Aadhaar blank.");
                }
                $aadhaar = '';
                $result['warnings'][] = "Row {$number} (employee {$employeeId}): invalid Aadhaar left blank.";
            }
            $attributes = [
                'employee_id' => $employeeId, 'name' => $name, 'father_name' => $father,
                'date_of_birth' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                'aadhaar_number' => $aadhaar, 'category' => $category, 'gender' => $gender,
                'designation' => $designation, 'entry_permit_number' => $permit,
                'verification_status' => $verification, 'contractor_id' => (int) $agencyMap[$agency], 'is_active' => true,
            ];
            $attributes = array_map(fn ($value) => $value === '' ? null : $value, $attributes);
            $validator = Validator::make($attributes, $rules);
            if ($validator->fails()) {
                $this->fail("Row {$number}: ".$validator->errors()->first());
            }
            $prepared[] = $attributes;
        }

        $result['created'] = count($prepared);
        if (! $dryRun) {
            DB::transaction(function () use ($prepared, $actor): void {
                foreach ($prepared as $attributes) {
                    $this->staff->save(null, $attributes, $actor);
                }
            });
        }

        return $result;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
