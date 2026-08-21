<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    protected function prepareForValidation(): void
    {
        $permissions = is_array($this->input('new_permissions'))
            ? $this->input('new_permissions')
            : array_values(array_filter(preg_split('/[\r\n,]+/', (string) $this->input('new_permissions')) ?: []));

        $this->merge(['new_permissions' => $permissions]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:125', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', Rule::unique(Role::class)->where('guard_name', 'web')->ignore($this->route('role'))],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists(Permission::class, 'name')->where('guard_name', 'web')],
            'new_permissions' => ['sometimes', 'array'],
            'new_permissions.*' => ['string', 'distinct', 'max:125', 'regex:/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/'],
        ];
    }
}
