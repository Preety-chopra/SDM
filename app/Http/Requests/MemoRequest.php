<?php

namespace App\Http\Requests;

use App\Enums\DeficiencyType;
use App\Enums\SdmType;
use App\Models\Contractor;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class MemoRequest extends FormRequest
{
    abstract protected function isSubmission(): bool;

    protected function prepareForValidation(): void
    {
        $incidentDate = $this->input('incident_date');
        if (is_string($incidentDate) && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/D', $incidentDate, $parts) === 1) {
            $this->merge(['incident_date' => "{$parts[3]}-{$parts[2]}-{$parts[1]}"]);
        }

        $lateReporting = $this->input('late_reporting');
        if (is_array($lateReporting)) {
            foreach (['booked_shift_from', 'booked_shift_to', 'actual_reporting_time'] as $field) {
                $time = $lateReporting[$field] ?? null;
                if (is_string($time) && preg_match('/^\d{2}:\d{2}:00$/D', $time) === 1) {
                    $lateReporting[$field] = substr($time, 0, 5);
                }
            }

            $this->merge(['late_reporting' => $lateReporting]);
        }

        $cashCheckSelected = in_array(
            DeficiencyType::CashCheck->value,
            (array) $this->input('deficiencies', []),
            true,
        );

        if (SdmType::tryFrom($this->string('service_type')->toString()) !== SdmType::Ticketing || ! $cashCheckSelected) {
            $this->request->remove('cash_check');
        }
    }

    public function rules(): array
    {
        $strict = $this->isSubmission();
        $types = $this->input('deficiencies', []);
        $serviceType = SdmType::tryFrom($this->string('service_type')->toString());
        $memo = $this->route('sdm_memo');
        $uniqueSerial = Rule::unique(ServiceDeficiencyMemo::class, 'serial_number')
            ->where(fn (Builder $query) => $query->where('service_type', $this->string('service_type')->toString()))
            ->ignore($memo instanceof ServiceDeficiencyMemo ? $memo->id : null);

        $rules = [
            'service_type' => ['required', Rule::enum(SdmType::class)],
            'serial_number' => ['required', 'string', 'max:50', $uniqueSerial],
            'contractor_id' => [$strict ? 'required' : 'nullable', Rule::exists(Contractor::class, 'id')->where(fn (Builder $query) => $query->where('is_active', true))],
            'incident_date' => [$strict ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'staff_name' => [$strict ? 'required' : 'nullable', 'string', 'max:255'],
            'employee_number' => [$strict ? 'required' : 'nullable', 'string', 'max:100'],
            'deficiencies' => [$strict ? 'required' : 'nullable', 'array', $strict ? 'min:1' : 'max:5'],
            'deficiencies.*' => [Rule::enum(DeficiencyType::class), 'distinct'],
            'deficiency_remarks' => ['nullable', 'array'],
            'deficiency_remarks.dress_code_or_instructions' => [Rule::requiredIf($strict && in_array(DeficiencyType::DressCode->value, $types, true)), 'nullable', 'string', 'max:5000'],
            'deficiency_remarks.misbehavior' => [Rule::requiredIf($strict && in_array(DeficiencyType::Misbehavior->value, $types, true)), 'nullable', 'string', 'max:5000'],
            'deficiency_remarks.other' => [Rule::requiredIf($strict && in_array(DeficiencyType::Other->value, $types, true)), 'nullable', 'string', 'max:5000'],
            'late_reporting' => ['nullable', 'array'],
            'late_reporting.booked_shift_from' => [Rule::requiredIf($strict && in_array(DeficiencyType::LateReporting->value, $types, true)), 'nullable', 'date_format:H:i'],
            'late_reporting.booked_shift_to' => [Rule::requiredIf($strict && in_array(DeficiencyType::LateReporting->value, $types, true)), 'nullable', 'date_format:H:i'],
            'late_reporting.actual_reporting_time' => [Rule::requiredIf($strict && in_array(DeficiencyType::LateReporting->value, $types, true)), 'nullable', 'date_format:H:i'],
            'public_complaint' => ['nullable', 'array'],
            'public_complaint.number' => ['nullable', 'string', 'max:100'],
            'public_complaint.station_id' => ['nullable', 'required_with:public_complaint.number', Rule::exists(Station::class, 'id')->where(fn (Builder $query) => $query->where('is_active', true))],
            'cash_check' => ['nullable', 'array'],
            'cash_check.tom_ccc_number' => ['nullable', 'string', 'max:100'],
            'cash_check.end_of_shift_receipt_number' => ['nullable', 'string', 'max:100'],
            'cash_check.check_date' => ['nullable', 'required_with:cash_check.check_time', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cash_check.check_time' => ['nullable', 'required_with:cash_check.check_date', 'date_format:H:i'],
            'cash_check.difference_amount' => ['nullable', 'numeric', 'between:-9999999999.99,9999999999.99'],
            'cash_check.hidden_cash_amount' => ['nullable', 'numeric', 'between:0,9999999999.99'],
            'cash_check.checking_official_name' => ['nullable', 'string', 'max:255'],
            'cash_check.checking_official_designation' => ['nullable', 'string', 'max:100'],
            'cash_check.checking_official_employee_number' => ['nullable', 'string', 'max:100'],
            'cash_check.remarks' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
        ];

        if ($serviceType !== SdmType::Ticketing) {
            $rules['cash_check'] = ['prohibited'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $types = $this->input('deficiencies', []);
            $cashSelected = in_array(DeficiencyType::CashCheck->value, $types, true);

            if ($cashSelected && SdmType::tryFrom($this->string('service_type')->toString()) !== SdmType::Ticketing) {
                $validator->errors()->add('deficiencies', 'TOM / CCC Inspection is available only for Ticketing memos.');
            }

            if ($this->isSubmission() && $cashSelected && collect($this->input('cash_check', []))->filter(fn ($value) => filled($value))->isEmpty()) {
                $validator->errors()->add('cash_check', 'Enter at least one TOM / CCC cash-check detail or remark.');
            }

            $memo = $this->route('sdm_memo');
            $existing = $memo instanceof ServiceDeficiencyMemo ? $memo->attachments()->count() : 0;
            if ($existing + count($this->file('attachments', [])) > 5) {
                $validator->errors()->add('attachments', 'A memo can contain no more than five attachments.');
            }
        }];
    }
}
