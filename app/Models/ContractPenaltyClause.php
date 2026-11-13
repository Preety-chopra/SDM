<?php

namespace App\Models;

use App\Enums\PenaltyCalculationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_contract_id', 'serial_number', 'description', 'calculation_type', 'base_amount', 'maximum_amount', 'cap_applicable', 'parameters', 'sort_order'])]
class ContractPenaltyClause extends Model
{
    public function contract(): BelongsTo
    {
        return $this->belongsTo(ServiceContract::class, 'service_contract_id');
    }

    public function wholeRupeeMaximum(): ?int
    {
        return $this->maximum_amount === null ? null : (int) floor((float) $this->maximum_amount);
    }

    public function wholeRupeeProposal(float $amount): float
    {
        $maximum = $this->wholeRupeeMaximum();

        return $this->calculation_type === PenaltyCalculationType::Maximum && $maximum !== null
            ? min(round($amount), $maximum)
            : round($amount);
    }

    public function suggestedAmount(array $inputs): ?float
    {
        return match ($this->calculation_type) {
            PenaltyCalculationType::Fixed => (float) $this->base_amount,
            PenaltyCalculationType::PerDayPerWorker => (float) $this->base_amount * (int) ($inputs['days'] ?? 0) * (int) ($inputs['workers'] ?? 0),
            PenaltyCalculationType::FixedPlusCost => (float) $this->base_amount + (float) ($inputs['additional_cost'] ?? 0),
            default => null,
        };
    }

    protected function casts(): array
    {
        return [
            'calculation_type' => PenaltyCalculationType::class,
            'base_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'cap_applicable' => 'boolean',
            'parameters' => 'array',
        ];
    }
}
