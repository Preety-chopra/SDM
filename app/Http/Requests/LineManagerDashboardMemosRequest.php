<?php

namespace App\Http\Requests;

use App\Enums\UserRole;

class LineManagerDashboardMemosRequest extends DashboardRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::AssistantManager->value) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'line_code' => ['required', 'string', 'max:30'],
        ];
    }

    public function lineCode(): string
    {
        return $this->string('line_code')->toString();
    }
}
