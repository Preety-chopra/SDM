<?php

namespace App\Support;

use App\Models\Contractor;
use Illuminate\Validation\Rule;

final class OutsourcedStaffRules
{
    public static function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'aadhaar_number' => ['nullable', 'string', 'regex:/^[0-9]{12}$/'],
            'category' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:255'],
            'entry_permit_number' => ['nullable', 'string', 'max:100'],
            'verification_status' => ['nullable', 'string', 'max:50'],
            'contractor_id' => ['required', 'integer', Rule::exists(Contractor::class, 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
