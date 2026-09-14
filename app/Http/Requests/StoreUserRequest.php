<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use App\Rules\Username;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as PermissionRole;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $role = UserRole::tryFrom($this->string('role')->toString());

        return [
            'name' => ['required', 'string', 'max:255', new Username, Rule::unique(User::class)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'role' => [
                'required',
                Rule::exists(PermissionRole::class, 'name')->where(fn (Builder $query) => $query
                    ->where('guard_name', 'web')
                    ->where('name', '!=', UserRole::Administrator->value)),
            ],
            'employee_number' => [Rule::requiredIf($role?->requiresEmployeeNumber() === true), 'nullable', 'string', 'max:100', Rule::unique(User::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'station_id' => [
                'nullable',
                Rule::exists(Station::class, 'id')->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->when($this->filled('line_id'), fn (Builder $query) => $query->where('line_id', $this->input('line_id')))),
            ],
            'line_id' => [
                'nullable',
                Rule::exists(Line::class, 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'is_enabled' => ['required', 'boolean'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
