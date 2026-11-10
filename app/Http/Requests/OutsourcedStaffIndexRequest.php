<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

class OutsourcedStaffIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageContractors->value);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'is_active' => ['nullable', 'boolean'],
            'verification_status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
