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

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $managedUser = $this->route('user');
        $role = UserRole::tryFrom($this->string('role')->toString());

        return [
            'name' => ['required', 'string', 'max:255', new Username, Rule::unique(User::class)->ignore($managedUser)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($managedUser)],
            'role' => ['required', Rule::in(UserRole::managedValues())],
            'employee_number' => [Rule::requiredIf($role?->requiresEmployeeNumber() === true), 'nullable', 'string', 'max:100', Rule::unique(User::class)->ignore($managedUser)],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'station_id' => [
                Rule::requiredIf($role?->requiresStation() === true),
                Rule::prohibitedIf($role !== null && ! $role->requiresStation()),
                'nullable',
                Rule::exists(Station::class, 'id')->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->when($role === UserRole::Station, fn (Builder $query) => $query->where('line_id', $this->input('line_id')))),
            ],
            'line_id' => [
                Rule::requiredIf($role === UserRole::Station),
                Rule::prohibitedIf($role !== null && $role !== UserRole::Station),
                'nullable',
                Rule::exists(Line::class, 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'is_enabled' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
