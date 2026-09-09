<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemoDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceDeficiencyMemo $memo */
        $memo = $this->route('sdm_memo');

        return $this->user()->can(UserPermission::RecordDeductions->value)
            && $this->user()->can('manageCmcLifecycle', $memo);
    }

    public function rules(): array
    {
        return [
            'bill_month' => ['required', 'date_format:Y-m'],
            'bill_reference' => ['required', 'string', 'max:100'],
            'deducted_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
