<?php

namespace App\Services;

use App\Enums\PenaltyCalculationType;
use App\Enums\WorkflowStepType;
use App\Models\ContractPenaltyClause;
use App\Models\MemoAssessment;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class MemoAssessmentService
{
    public function create(
        ServiceDeficiencyMemo $memo,
        User $actor,
        WorkflowStepType $stage,
        int $round,
        array $data,
    ): MemoAssessment {
        $items = collect($data['items'] ?? []);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'At least one contractual clause is required.']);
        }

        $version = ((int) $memo->assessments()->max('version')) + 1;
        $monthlyBill = isset($data['monthly_bill_value']) ? (float) $data['monthly_bill_value'] : null;
        $prepared = $items->map(function (array $item) use ($memo): array {
            $clause = ContractPenaltyClause::query()
                ->whereBelongsTo($memo->serviceContract, 'contract')
                ->findOrFail($item['contract_penalty_clause_id']);
            $inputs = $item['calculation_inputs'] ?? [];
            $suggested = $clause->suggestedAmount($inputs);
            $proposed = round((float) $item['proposed_amount'], 2);

            if ($proposed < 0) {
                throw ValidationException::withMessages(['items' => 'Penalty amounts cannot be negative.']);
            }

            if ($clause->calculation_type === PenaltyCalculationType::Maximum
                && $clause->maximum_amount !== null
                && $proposed > (float) $clause->maximum_amount) {
                throw ValidationException::withMessages([
                    'items' => "Clause {$clause->serial_number} cannot exceed ₹{$clause->maximum_amount}.",
                ]);
            }

            if (in_array($clause->calculation_type, [
                PenaltyCalculationType::Fixed,
                PenaltyCalculationType::PerDayPerWorker,
                PenaltyCalculationType::FixedPlusCost,
            ], true) && $suggested !== null && abs($proposed - $suggested) > 0.009) {
                throw ValidationException::withMessages([
                    'items' => "Clause {$clause->serial_number} must use the contractual calculation of ₹".number_format($suggested, 2).'.',
                ]);
            }

            return [
                'contract_penalty_clause_id' => $clause->id,
                'clause_serial_number' => $clause->serial_number,
                'clause_description' => $clause->description,
                'calculation_type' => $clause->calculation_type->value,
                'base_amount' => $clause->base_amount,
                'maximum_amount' => $clause->maximum_amount,
                'cap_applicable' => $clause->cap_applicable,
                'calculation_inputs' => $inputs === [] ? null : $inputs,
                'suggested_amount' => $suggested,
                'proposed_amount' => $proposed,
                'justification' => $item['justification'],
            ];
        });

        $total = round((float) $prepared->sum('proposed_amount'), 2);
        $cappedTotal = round((float) $prepared->where('cap_applicable', true)->sum('proposed_amount'), 2);
        $capPercentage = $monthlyBill && $monthlyBill > 0 ? round(($cappedTotal / $monthlyBill) * 100, 3) : null;

        $assessment = $memo->assessments()->create([
            'round' => $round,
            'version' => $version,
            'source_stage' => $stage->value,
            'proposed_by' => $actor->id,
            'negative_points' => $data['negative_points'] ?? null,
            'staff_action' => $data['staff_action'] ?? null,
            'bill_month' => isset($data['bill_month']) ? Carbon::createFromFormat('Y-m', $data['bill_month'])->startOfMonth() : null,
            'monthly_bill_value' => $monthlyBill,
            'proposed_total' => $total,
            'cap_percentage' => $capPercentage,
            'cap_warning' => $capPercentage !== null && $capPercentage >= 10,
            'status' => 'proposed',
        ]);
        $assessment->items()->createMany($prepared->all());

        return $assessment->load('items');
    }
}
