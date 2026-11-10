<?php

namespace App\Http\Requests;

use App\Models\ServiceDeficiencyMemo;
use Illuminate\Foundation\Http\FormRequest;

class LookupOutsourcedStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $memo = $this->route('sdm_memo');

        return $memo instanceof ServiceDeficiencyMemo
            ? $this->user()->can('update', $memo)
            : $this->user()->can('create', ServiceDeficiencyMemo::class);
    }

    public function rules(): array
    {
        return [
            'staff_identifier' => ['required', 'string', 'max:100'],
            'contractor_id' => ['required', 'integer', 'exists:contractors,id'],
        ];
    }
}
