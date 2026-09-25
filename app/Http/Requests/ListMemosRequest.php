<?php

namespace App\Http\Requests;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\WorkflowStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMemosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'pending_stage' => ['nullable', Rule::enum(WorkflowStage::class)],
            'category' => ['nullable', Rule::in(['pending', 'transferred'])],
            'service_type' => ['nullable', Rule::enum(SdmType::class)],
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')],
            'line_code' => ['nullable', 'string', Rule::exists('lines', 'code')],
            'incident_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(MemoStatus::class)],
        ];
    }

    public function pendingStage(): ?WorkflowStage
    {
        return WorkflowStage::tryFrom($this->string('pending_stage')->toString());
    }

    public function category(): ?string
    {
        $category = $this->string('category')->toString();

        return in_array($category, ['pending', 'transferred'], true) ? $category : null;
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
