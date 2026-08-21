<?php

namespace App\Http\Requests;

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
        ];
    }

    public function pendingStage(): ?WorkflowStage
    {
        return WorkflowStage::tryFrom($this->string('pending_stage')->toString());
    }
}
