<?php

namespace App\Http\Requests;

use App\Enums\AmCmcMemoCategory;
use App\Enums\AmCmcReceivedSdmTab;
use App\Enums\CmcTeamLeadMemoCategory;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\StationManagerMemoStatus;
use App\Enums\UserRole;
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
            'category' => ['nullable', Rule::in($this->allowedCategories())],
            'received_tab' => ['nullable', 'prohibited_unless:category,'.AmCmcMemoCategory::ReceivedFromLines->value, Rule::enum(AmCmcReceivedSdmTab::class)],
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
            'status' => ['nullable', Rule::enum($this->isStationManager() ? StationManagerMemoStatus::class : MemoStatus::class)],
            'acknowledge_notifications' => ['nullable', 'boolean'],
        ];
    }

    public function shouldAcknowledgeNotifications(): bool
    {
        return $this->boolean('acknowledge_notifications');
    }

    public function pendingStage(): ?WorkflowStage
    {
        return WorkflowStage::tryFrom($this->string('pending_stage')->toString());
    }

    public function category(): ?string
    {
        return $this->validated('category');
    }

    public function receivedTab(): ?AmCmcReceivedSdmTab
    {
        if ($this->category() !== AmCmcMemoCategory::ReceivedFromLines->value) {
            return null;
        }

        return $this->enum('received_tab', AmCmcReceivedSdmTab::class) ?? AmCmcReceivedSdmTab::Pending;
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

    private function isStationManager(): bool
    {
        return $this->user()?->hasRole(UserRole::StationManager->value) ?? false;
    }

    /** @return list<string> */
    private function allowedCategories(): array
    {
        $categories = ['pending', 'transferred', 'pending_action', 'urgent_pending_action'];

        if ($this->user()?->hasRole(UserRole::ManagerOperations->value)) {
            $categories = [...$categories, ...array_column(AmCmcMemoCategory::cases(), 'value')];
        }

        if ($this->user()?->hasRole(UserRole::CmcTeamLead->value)) {
            $categories = [...$categories, ...array_column(CmcTeamLeadMemoCategory::cases(), 'value'), AmCmcMemoCategory::ApprovedByDgmCmc->value];
        }

        return $categories;
    }
}
