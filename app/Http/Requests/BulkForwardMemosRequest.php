<?php

namespace App\Http\Requests;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\WorkflowStepType;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkForwardMemosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bulkForward', ServiceDeficiencyMemo::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'destination' => ['required', Rule::in([
                WorkflowStepType::AmCmcToScrutiny->value,
                WorkflowStepType::AmCmcToDgm->value,
            ])],
            'select_all' => ['nullable', 'boolean'],
            'memo_ids' => [Rule::requiredIf(! $this->boolean('select_all')), 'nullable', 'array', 'min:1'],
            'memo_ids.*' => ['required', 'integer', 'distinct', Rule::exists(ServiceDeficiencyMemo::class, 'id')],
            'service_type' => ['nullable', Rule::enum(SdmType::class)],
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')],
            'line_code' => ['nullable', 'string', Rule::exists('lines', 'code')],
            'incident_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(MemoStatus::class)],
        ];
    }

    public function destination(): WorkflowStepType
    {
        return WorkflowStepType::from($this->string('destination')->toString());
    }

    /** @return list<int> */
    public function memoIds(): array
    {
        return collect($this->validated('memo_ids', []))->map(fn (mixed $id): int => (int) $id)->all();
    }

    public function selectsAll(): bool
    {
        return $this->boolean('select_all');
    }

    /** @return array<string, int|string> */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['service_type', 'station_id', 'line_code', 'incident_date', 'status']),
            fn (mixed $value): bool => filled($value),
        );
    }
}
