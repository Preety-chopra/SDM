<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageStations->value);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique(Station::class)->ignore($this->route('station'))],
            'name' => ['required', 'string', 'max:255'],
            'line_id' => ['required', Rule::exists(Line::class, 'id')->where(fn (Builder $query) => $query->where('is_active', true))],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
