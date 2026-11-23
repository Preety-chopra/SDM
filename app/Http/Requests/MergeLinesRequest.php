<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;

class MergeLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageLines->value);
    }

    public function rules(): array
    {
        return [
            'line_ids' => ['required', 'array', 'min:2'],
            'line_ids.*' => ['required', 'integer', 'distinct'],
            'target_line_id' => ['required', 'integer'],
        ];
    }
}
