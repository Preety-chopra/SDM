<?php

namespace App\Http\Requests;

use App\Enums\AmCmcMemoCategory;
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
        return $this->user()->can('bulkAmCmcAction', ServiceDeficiencyMemo::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::in([AmCmcMemoCategory::ReceivedFromLines->value])],
            'destination' => ['required', Rule::in($this->isReceivedFromLines()
                ? [WorkflowStepType::AmCmcToScrutiny->value]
                : [WorkflowStepType::AmCmcToScrutiny->value, WorkflowStepType::AmCmcToDgm->value])],
            'select_all' => ['nullable', 'boolean', 'prohibited_if:category,'.AmCmcMemoCategory::ReceivedFromLines->value],
            'memo_ids' => [Rule::requiredIf(! $this->boolean('select_all')), 'nullable', 'array', 'min:1'],
            'memo_ids.*' => ['required', 'integer', 'distinct', Rule::exists(ServiceDeficiencyMemo::class, 'id')],
            'service_type' => ['nullable', Rule::enum(SdmType::class)],
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')],
            'line_code' => ['nullable', 'string', Rule::exists('lines', 'code')],
            'incident_date' => ['nullable', 'date_format:Y-m-d'],
            'incident_date_from' => ['nullable', 'date_format:Y-m-d'],
            'incident_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->filled('incident_date_from'), ['after_or_equal:incident_date_from']),
            ],
            'status' => ['nullable', Rule::enum(MemoStatus::class)],
        ];
    }

    public function isReceivedFromLines(): bool
    {
        return $this->input('category') === AmCmcMemoCategory::ReceivedFromLines->value;
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
            $this->safe()->only([
                'service_type',
                'station_id',
                'line_code',
                'incident_date',
                'incident_date_from',
                'incident_date_to',
                'status',
            ]),
            fn (mixed $value): bool => filled($value),
        );
    }
}
