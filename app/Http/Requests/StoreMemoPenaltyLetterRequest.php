<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemoPenaltyLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceDeficiencyMemo $memo */
        $memo = $this->route('sdm_memo');

        return $this->user()->can(UserPermission::ManagePenaltyLetters->value)
            && $this->user()->can('manageCmcLifecycle', $memo);
    }

    public function rules(): array
    {
        return [
            'issued_at' => ['required', 'date'],
            'delivery_details' => ['required', 'string', 'max:5000'],
            'letter' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ];
    }
}
