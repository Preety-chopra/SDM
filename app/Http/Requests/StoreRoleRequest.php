<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['new_permissions' => $this->permissionNames()]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:125', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', Rule::unique(Role::class)->where('guard_name', 'web')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists(Permission::class, 'name')->where('guard_name', 'web')],
            'new_permissions' => ['sometimes', 'array'],
            'new_permissions.*' => ['string', 'distinct', 'max:125', 'regex:/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/'],
        ];
    }

    /** @return list<string> */
    private function permissionNames(): array
    {
        if (is_array($this->input('new_permissions'))) {
            return $this->input('new_permissions');
        }

        return array_values(array_filter(preg_split('/[\r\n,]+/', (string) $this->input('new_permissions')) ?: []));
    }
}
