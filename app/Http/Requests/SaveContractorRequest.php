<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\Contractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageContractors->value);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Contractor::class)->ignore($this->route('contractor'))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Contractor::class)->ignore($this->route('contractor'))],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
