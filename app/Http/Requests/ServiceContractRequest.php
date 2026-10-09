<?php

namespace App\Http\Requests;

use App\Enums\PenaltyCalculationType;
use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ServiceContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ManageContracts->value);
    }

    public function rules(): array
    {
        return [
            'contractor_id' => ['required', Rule::exists(Contractor::class, 'id')->where('is_active', true)],
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['required', 'integer', 'distinct', Rule::exists(Line::class, 'id')->where('is_active', true)],
            'station_ids' => ['required', 'array', 'min:1'],
            'station_ids.*' => ['required', 'integer', 'distinct', Rule::exists(Station::class, 'id')->where('is_active', true)],
            'service_type' => ['required', Rule::enum(SdmType::class)],
            'contract_number' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'cmc_staff_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('is_enabled', true)],
            'source_document' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            'clauses' => ['sometimes', 'array', 'min:1'],
            'clauses.*.serial_number' => ['required_with:clauses', 'integer', 'min:1', 'distinct'],
            'clauses.*.description' => ['required_with:clauses', 'string', 'max:5000'],
            'clauses.*.calculation_type' => ['required_with:clauses', Rule::enum(PenaltyCalculationType::class)],
            'clauses.*.base_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'clauses.*.maximum_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'clauses.*.cap_applicable' => ['required_with:clauses', 'boolean'],
            'clauses.*.parameters' => ['nullable', 'array'],
            'clauses.*.sort_order' => ['required_with:clauses', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $lineIds = $this->collect('line_ids')->map(fn (mixed $id): int => (int) $id)->unique();
                $stationIds = $this->collect('station_ids')->map(fn (mixed $id): int => (int) $id)->unique();

                if ($lineIds->isNotEmpty() && $stationIds->isNotEmpty()) {
                    $stationLineIds = Station::query()
                        ->whereKey($stationIds)
                        ->pluck('line_id')
                        ->unique();

                    if ($stationLineIds->diff($lineIds)->isNotEmpty()) {
                        $validator->errors()->add('station_ids', 'Every selected station must belong to one of the selected lines.');
                    }

                    if ($lineIds->diff($stationLineIds)->isNotEmpty()) {
                        $validator->errors()->add('line_ids', 'Select at least one station for every selected line.');
                    }
                }

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
}
