<?php

namespace App\Http\Requests;

use App\Enums\WorkflowStage;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $isCancel = $this->string('action')->toString() === 'cancel';
        $requiresEvidence = $isCancel && $memo->current_stage === WorkflowStage::StationManager;

        return [
            'action' => ['required', Rule::in(['forward', 'approve', 'cancel'])],
            'remarks' => [Rule::requiredIf($isCancel), 'nullable', 'string', 'max:5000'],
            'evidence' => [Rule::requiredIf($requiresEvidence), 'array', 'max:10'],
            'evidence.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
