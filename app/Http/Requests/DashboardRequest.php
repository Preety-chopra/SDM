<?php

namespace App\Http\Requests;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
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
            'status' => ['nullable', $this->isStationController()
                ? Rule::in([
                    MemoStatus::Draft->value,
                    MemoStatus::AwaitingStationManager->value,
                    MemoStatus::LineReview->value,
                ])
                : Rule::enum(MemoStatus::class)],
            'aging' => ['nullable', Rule::in(['0_2', '3_7', '8_15', 'over_15'])],
        ];
    }

    public function dateFrom(): CarbonImmutable
    {
        $value = $this->validated('date_from');

        return filled($value)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value)
            : $this->defaultDateFrom();
    }

    public function dateTo(): CarbonImmutable
    {
        $value = $this->validated('date_to');

        return filled($value)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value)->endOfDay()
            : $this->defaultDateTo();
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

    private function isStationController(): bool
    {
        return $this->user()?->getRoleNames()->contains(
            fn (string $role): bool => in_array($role, [UserRole::Station->value, 'station_controller'], true),
        ) ?? false;
    }

    private function defaultDateFrom(): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        if (! $this->usesHalfMonthlyReportingWindow() || $now->day <= 15) {
            return $now->startOfMonth();
        }

        return $now->startOfMonth()->addDays(15);
    }

    private function defaultDateTo(): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        if (! $this->usesHalfMonthlyReportingWindow() || $now->day > 15) {
            return $now->endOfMonth();
        }

        return $now->startOfMonth()->addDays(14)->endOfDay();
    }

    private function usesHalfMonthlyReportingWindow(): bool
    {
        return $this->user()?->hasAnyRole([
            UserRole::StationManager->value,
            UserRole::AssistantManager->value,
        ]) ?? false;
    }
}
