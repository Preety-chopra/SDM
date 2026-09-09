<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMemoCmcAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceDeficiencyMemo $memo */
        $memo = $this->route('sdm_memo');

        return $this->user()->can('assignCmcStaff', $memo);
    }

    public function rules(): array
    {
        return [
            'cmc_staff_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->where('is_enabled', true),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $cmcStaff = User::query()->find($this->integer('cmc_staff_id'));
                if ($cmcStaff && ! $cmcStaff->hasRole(UserRole::CmcStaff->value)) {
                    $validator->errors()->add('cmc_staff_id', 'Select an enabled CMC Staff user.');
                }
            },
        ];
    }
}
