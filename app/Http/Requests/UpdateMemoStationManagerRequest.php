<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMemoStationManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reassignStationManager', $this->route('sdm_memo'));
    }

    public function rules(): array
    {
        return [
            'station_manager_employee_number' => ['required', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('station_manager_employee_number')) {
                return;
            }

            $stationManager = User::query()
                ->role(UserRole::StationManager->value)
                ->where('employee_number', $this->string('station_manager_employee_number')->toString())
                ->where('is_enabled', true)
                ->first();

            if (! $stationManager) {
                $validator->errors()->add('station_manager_employee_number', 'Enter the employee number of an enabled Station Manager.');

                return;
            }

            $memo = $this->route('sdm_memo');
            if (! $memo instanceof ServiceDeficiencyMemo) {
                return;
            }

            $assignedTo = $memo->workflow_version >= 2
                ? $memo->currentWorkflowStep?->assigned_to
                : $memo->approvals()->where('role', WorkflowStage::StationManager->value)->value('assigned_to');

            if ($assignedTo === $stationManager->id) {
                $validator->errors()->add('station_manager_employee_number', 'Select a different Station Manager.');
            }
        }];
    }
}
