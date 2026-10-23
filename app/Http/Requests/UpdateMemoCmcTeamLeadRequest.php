<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMemoCmcTeamLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reassignCmcTeamLead', $this->route('sdm_memo'));
    }

    public function rules(): array
    {
        return [
            'cmc_team_lead_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->where('is_enabled', true),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('cmc_team_lead_id')) {
                return;
            }

            $cmcTeamLead = User::query()->find($this->integer('cmc_team_lead_id'));
            if (! $cmcTeamLead?->hasRole(UserRole::CmcTeamLead->value)) {
                $validator->errors()->add('cmc_team_lead_id', 'Select an enabled SM/CMC user.');

                return;
            }

            $memo = $this->route('sdm_memo');
            if ($memo instanceof ServiceDeficiencyMemo
                && $memo->currentWorkflowStep?->assigned_to === $cmcTeamLead->id) {
                $validator->errors()->add('cmc_team_lead_id', 'Select a different SM/CMC user.');
            }
        }];
    }
}
