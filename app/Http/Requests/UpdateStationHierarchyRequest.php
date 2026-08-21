<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStationHierarchyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageSdmHierarchy->value);
    }

    public function rules(): array
    {
        return [
            'subordinate_id' => ['required', Rule::exists(User::class, 'id')->where('is_enabled', true)],
            'supervisor_id' => ['required', 'different:subordinate_id', Rule::exists(User::class, 'id')->where('is_enabled', true)],
        ];
    }
}
