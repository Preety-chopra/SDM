<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMemoLineManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('handoverLineManager', $this->route('sdm_memo'));
    }

    public function rules(): array
    {
        return [
            'line_manager_employee_number' => ['required', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('line_manager_employee_number')) {
                return;
            }

            $lineManager = User::query()
                ->role(UserRole::AssistantManager->value)
                ->where('employee_number', $this->string('line_manager_employee_number')->toString())
                ->where('is_enabled', true)
                ->first();

            if (! $lineManager) {
                $validator->errors()->add('line_manager_employee_number', 'Enter the employee number of an enabled AM/Manager (Line).');

                return;
            }

            $memo = $this->route('sdm_memo');
            if (! $memo instanceof ServiceDeficiencyMemo) {
                return;
            }

            $assignedTo = $memo->workflow_version >= 2
                ? $memo->currentWorkflowStep?->assigned_to
                : $memo->approvals()->where('role', WorkflowStage::LineManager->value)->value('assigned_to');

            if ($assignedTo === $lineManager->id) {
                $validator->errors()->add('line_manager_employee_number', 'Select a different AM/Manager (Line).');
            }
        }];
    }
}
