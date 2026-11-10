<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

class ImportOutsourcedStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageContractors->value);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,zip', 'extensions:xlsx', 'max:10240'],
            'agency_map' => ['sometimes', 'array'],
            'agency_map.*' => ['nullable', 'integer', 'exists:contractors,id'],
            'invalid_aadhaar' => ['required', 'in:reject,blank'],
        ];
    }
}
