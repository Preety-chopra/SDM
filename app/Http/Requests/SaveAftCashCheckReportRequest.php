<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\AftCashCheckReport;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAftCashCheckReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('aft_cash_check_report');
        $submitting = $this->input('action') === 'submit';

        return ($report instanceof AftCashCheckReport
            ? $this->user()->can($submitting ? 'submit' : 'update', $report)
            : $this->user()->can('create', AftCashCheckReport::class))
            && (! $submitting || $this->user()->can(UserPermission::SubmitOwnDraftMemos->value));
    }

    public function rules(): array
    {
        $required = $this->input('action') === 'submit' ? 'required' : 'nullable';
        $rules = [
            'action' => ['required', Rule::in(['save', 'submit'])],
            'entries' => ['required', 'array', 'min:1', 'max:100'],
            'entries.*' => ['required', 'array'],
            'entries.*.check_date' => [$required, 'date_format:Y-m-d', 'before_or_equal:today'],
            'entries.*.check_time' => [$required, 'date_format:H:i'],
            'entries.*.station_id' => [$required, 'integer', Rule::exists(Station::class, 'id')->where(fn (Builder $query) => $query
                ->where('is_active', true)->whereIn('line_id', Line::query()->select('id')->where('is_active', true)))],
            'entries.*.operator_name' => [$required, 'string', 'max:255'],
            'entries.*.remarks' => ['prohibited'],
            'entries.*.cumulative_negative_points' => ['prohibited'],
            'entries.*.penalty_amount' => ['prohibited'],
        ];
        foreach (['check_type', 'tom_number', 'employee_number', 'employer', 'eos_number'] as $field) {
            $rules['entries.*.'.$field] = [$required, 'string', 'max:100'];
        }
        foreach (['cash_as_per_eos', 'actual_cash', 'hidden_amount', 'cash_deposit_at_station'] as $field) {
            $rules['entries.*.'.$field] = [$required, ...self::amountRules()];
        }

        return $rules;
    }

    public static function amountRules(): array
    {
        return ['nullable', 'numeric', 'between:0,999999999999.99', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'];
    }

    public function messages(): array
    {
        return [
            'entries.*.station_id.exists' => 'Choose an active station on an active line.',
            'entries.*.remarks.prohibited' => 'Remarks are completed by CMC Staff during review.',
            'entries.*.cumulative_negative_points.prohibited' => 'Cumulative negative points are completed by CMC Staff during review.',
            'entries.*.penalty_amount.prohibited' => 'Penalty amounts are completed by CMC Staff during review.',
        ];
    }
}
