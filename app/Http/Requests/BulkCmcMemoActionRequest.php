<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkCmcMemoActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bulkCmcAction', ServiceDeficiencyMemo::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $action = $this->string('action')->toString();

        return [
            'action' => ['required', Rule::in(['assign', 'forward', 'return'])],
            'memo_ids' => ['required', 'array', 'min:1'],
            'memo_ids.*' => ['required', 'integer', 'distinct', Rule::exists(ServiceDeficiencyMemo::class, 'id')],
            'cmc_staff_id' => [
                Rule::requiredIf($action === 'assign'),
                'nullable',
                'integer',
                Rule::exists(User::class, 'id')->where('is_enabled', true),
            ],
            'remarks' => [Rule::requiredIf($action === 'return'), 'nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('cmc_staff_id')) {
                    return;
                }

                $cmcStaff = User::query()->find($this->integer('cmc_staff_id'));
                if ($cmcStaff && ! $cmcStaff->hasRole(UserRole::CmcStaff->value)) {
                    $validator->errors()->add('cmc_staff_id', 'Select an enabled CMC Staff user.');
                }
            },
        ];
    }

    /** @return list<int> */
    public function memoIds(): array
    {
        return collect($this->validated('memo_ids'))->map(fn (mixed $id): int => (int) $id)->all();
    }
}
