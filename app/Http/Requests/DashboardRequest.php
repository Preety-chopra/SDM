<?php

namespace App\Http\Requests;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'service_type' => ['nullable', Rule::enum(SdmType::class)],
            'status' => ['nullable', Rule::enum(MemoStatus::class)],
            'aging' => ['nullable', Rule::in(['0_2', '3_7', '8_15', 'over_15'])],
        ];
    }

    public function dateFrom(): CarbonImmutable
    {
        $value = $this->validated('date_from');

        return filled($value)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value)
            : CarbonImmutable::now()->startOfMonth();
    }

    public function dateTo(): CarbonImmutable
    {
        $value = $this->validated('date_to');

        return filled($value)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value)->endOfDay()
            : CarbonImmutable::now()->endOfMonth();
    }

    public function serviceType(): ?SdmType
    {
        return SdmType::tryFrom($this->string('service_type')->toString());
    }

    public function status(): ?MemoStatus
    {
        return MemoStatus::tryFrom($this->string('status')->toString());
    }

    public function aging(): ?string
    {
        $aging = $this->string('aging')->toString();

        return $aging !== '' ? $aging : null;
    }
}
