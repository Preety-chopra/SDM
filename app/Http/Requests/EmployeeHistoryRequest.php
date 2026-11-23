<?php

namespace App\Http\Requests;

use App\Models\ServiceDeficiencyMemo;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewEmployeeHistory', ServiceDeficiencyMemo::class);
    }

    public function rules(): array
    {
        return [
            'contractor_id' => ['nullable', 'required_with:employee_number', 'integer', 'exists:contractors,id'],
            'employee_number' => ['nullable', 'required_with:contractor_id', 'string', 'max:100'],
            'memos_page' => ['nullable', 'integer', 'min:1'],
            'historical_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
