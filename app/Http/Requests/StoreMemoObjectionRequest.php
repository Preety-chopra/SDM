<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemoObjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceDeficiencyMemo $memo */
        $memo = $this->route('sdm_memo');

        return $this->user()->can(UserPermission::ManageObjections->value)
            && $this->user()->can('manageCmcLifecycle', $memo);
    }

    public function rules(): array
    {
        return [
            'received_at' => ['required', 'date'],
            'remarks' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ];
    }
}
