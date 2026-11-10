<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\OutsourcedStaff;
use App\Support\OutsourcedStaffRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOutsourcedStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageContractors->value);
    }

    public function rules(): array
    {
        $rules = OutsourcedStaffRules::rules();
        $rules['employee_id'][] = Rule::unique(OutsourcedStaff::class)->ignore($this->route('outsourced_staff'));
        $rules['clear_aadhaar'] = ['sometimes', 'boolean'];

        return $rules;
    }

    public function messages(): array
    {
        return ['aadhaar_number.regex' => 'Enter a 12-digit Aadhaar number.'];
    }
}
