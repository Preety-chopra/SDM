<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMemoWorkflowActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('act', $this->route('sdm_memo'));
    }

    public function rules(): array
    {
        /** @var ServiceDeficiencyMemo $memo */
        $memo = $this->route('sdm_memo');
        if ($memo->workflow_version >= 2) {
            return $this->versionTwoRules($memo);
        }

        $isCancel = $this->string('action')->toString() === 'cancel';
        $stage = $memo->current_stage;

        return [
            'action' => ['required', Rule::in(['forward', 'approve', 'cancel'])],
            'forward_to_employee_number' => [
                Rule::requiredIf($stage === WorkflowStage::StationManager && $this->string('action')->toString() === 'forward'),
                'nullable',
                'string',
                'max:100',
            ],
            'remarks' => [Rule::requiredIf($isCancel), 'nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array', 'max:10'],
            'evidence.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    private function versionTwoRules(ServiceDeficiencyMemo $memo): array
    {
        $action = $this->string('action')->toString();
        $stage = $memo->currentWorkflowStep?->stage;
        if ($stage === WorkflowStepType::CmcAssignment) {
            return [
                'action' => ['required', Rule::in(['assign'])],
                'cmc_staff_id' => [
                    'required',
                    'integer',
                    Rule::exists(User::class, 'id')->where('is_enabled', true),
                ],
            ];
        }

        $requiresAssessment = in_array($stage, [WorkflowStepType::LineReview, WorkflowStepType::CmcAssessment], true)
            && $action === 'forward';
        $requiresCmcAssessment = $stage === WorkflowStepType::CmcAssessment && $action === 'forward';

        return [
            'action' => ['required', Rule::in(['forward', 'approve', 'return', 'cancel'])],
            'forward_to_employee_number' => [
                Rule::requiredIf($stage === WorkflowStepType::StationManager && $action === 'forward'),
                'nullable',
                'string',
                'max:100',
            ],
            'remarks' => [Rule::requiredIf(in_array($action, ['return', 'cancel'], true)), 'nullable', 'string', 'max:5000'],
            'items' => [Rule::requiredIf($requiresAssessment), 'nullable', 'array', 'min:1'],
            'items.*.contract_penalty_clause_id' => ['required', 'integer', 'exists:contract_penalty_clauses,id'],
            'items.*.proposed_amount' => ['required', 'numeric', 'min:0'],
            'items.*.justification' => ['nullable', 'string', 'max:5000'],
            'items.*.calculation_inputs' => ['nullable', 'array'],
            'items.*.calculation_inputs.days' => ['nullable', 'integer', 'min:0'],
            'items.*.calculation_inputs.workers' => ['nullable', 'integer', 'min:0'],
            'items.*.calculation_inputs.additional_cost' => ['nullable', 'numeric', 'min:0'],
            'negative_points' => [Rule::requiredIf($requiresCmcAssessment), 'nullable', 'integer', 'min:0'],
            'staff_action' => [Rule::requiredIf($requiresCmcAssessment), 'nullable', Rule::in(['none', 'warning', 'final_warning', 'recall'])],
            'bill_month' => [Rule::requiredIf($requiresCmcAssessment), 'nullable', 'date_format:Y-m'],
            'monthly_bill_value' => [Rule::requiredIf($requiresCmcAssessment), 'nullable', 'numeric', 'gt:0'],
            'cap_acknowledged' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('cmc_staff_id')) {
                    if ($this->filled('forward_to_employee_number')) {
                        $lineManager = User::query()
                            ->role(UserRole::AssistantManager->value)
                            ->where('employee_number', $this->string('forward_to_employee_number')->toString())
                            ->where('is_enabled', true)
                            ->exists();
                        if (! $lineManager) {
                            $validator->errors()->add('forward_to_employee_number', 'Enter the employee number of an enabled AM/Line user.');
                        }
                    }

                    return;
                }

                $cmcStaff = User::query()->find($this->integer('cmc_staff_id'));
                if ($cmcStaff && ! $cmcStaff->hasRole(UserRole::CmcStaff->value)) {
                    $validator->errors()->add('cmc_staff_id', 'Select an enabled CMC Staff user.');
                }
            },
        ];
    }
}
