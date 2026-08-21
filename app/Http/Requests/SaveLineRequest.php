<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\Line;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageLines->value);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique(Line::class)->ignore($this->route('line'))],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
