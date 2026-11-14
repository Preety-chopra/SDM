<?php

namespace App\Http\Requests;

use App\Models\ServiceContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateContractStationMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('contract'));
    }

    public function rules(): array
    {
        return [
            'replacement_contract_id' => ['bail', 'required', 'integer', Rule::exists(ServiceContract::class, 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'replacement_contract_id.required' => 'Select a replacement contract.',
            'replacement_contract_id.integer' => 'Select a valid replacement contract.',
            'replacement_contract_id.exists' => 'The selected replacement contract is no longer available.',
        ];
    }
}
