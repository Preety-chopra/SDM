<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineSectionsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('sections') === '' || ($this->exists('sections') && $this->input('sections') === null)) {
            $this->merge(['sections' => []]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageLines->value);
    }

    public function rules(): array
    {
        return [
            'sections' => ['present', 'array', 'max:50'],
            'sections.*' => ['required', 'array:code,name,selection_mode,station_ids,from_station_id,to_station_id'],
            'sections.*.code' => ['required', 'string', 'max:30', 'distinct:ignore_case', 'regex:/^[A-Za-z0-9-]+$/'],
            'sections.*.name' => ['required', 'string', 'max:255'],
            'sections.*.selection_mode' => ['required', Rule::in(['stations', 'range'])],
            'sections.*.station_ids' => ['required_if:sections.*.selection_mode,stations', 'array', 'min:1'],
            'sections.*.station_ids.*' => ['required', 'integer', 'distinct'],
            'sections.*.from_station_id' => ['nullable', 'required_if:sections.*.selection_mode,range', 'integer'],
            'sections.*.to_station_id' => ['nullable', 'required_if:sections.*.selection_mode,range', 'integer'],
        ];
    }
}
