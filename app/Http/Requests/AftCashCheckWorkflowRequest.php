<?php

namespace App\Http\Requests;

use App\Enums\WorkflowStepType;
use App\Models\AftCashCheckReport;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AftCashCheckWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('act', $this->route('aft_cash_check_report'));
    }

    public function rules(): array
    {
        /** @var AftCashCheckReport $report */
        $report = $this->route('aft_cash_check_report');
        $stage = $report->currentStep?->stage;
        $review = $stage === WorkflowStepType::CmcAssessment && $this->input('action') === 'forward';
        $actions = match ($stage) {
            WorkflowStepType::CmcAssignment => ['assign'],
            WorkflowStepType::CmcVerification, WorkflowStepType::AmCmcToDgm => ['forward', 'return'],
            WorkflowStepType::DgmApproval => ['approve', 'return', 'cancel'],
            default => ['forward'],
        };

        return [
            'action' => ['required', Rule::in($actions)],
            'remarks' => [Rule::requiredIf(in_array($this->input('action'), ['return', 'cancel'], true)), 'nullable', 'string', 'max:5000'],
            'cmc_staff_id' => [Rule::requiredIf($stage === WorkflowStepType::CmcAssignment), Rule::prohibitedIf($stage !== WorkflowStepType::CmcAssignment), 'nullable', 'integer', Rule::exists(User::class, 'id')->where('is_enabled', true)],
            'reviews' => [Rule::requiredIf($review), Rule::prohibitedIf(! $review), 'array', 'size:'.$report->entries()->count()],
            'reviews.*.entry_id' => ['required', 'integer', 'distinct', Rule::exists('aft_cash_check_entries', 'id')->where('report_id', $report->id)],
            'reviews.*.remarks' => ['nullable', 'string', 'max:5000'],
            'reviews.*.cumulative_negative_points' => ['required', 'integer', 'between:0,1000000000'],
            'reviews.*.penalty_amount' => ['required', ...SaveAftCashCheckReportRequest::amountRules()],
        ];
    }
}
