<?php

namespace App\Models;

use App\Enums\PenaltyCalculationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_assessment_id', 'contract_penalty_clause_id', 'clause_serial_number', 'clause_description', 'calculation_type', 'base_amount', 'maximum_amount', 'cap_applicable', 'calculation_inputs', 'suggested_amount', 'proposed_amount', 'justification'])]
class MemoAssessmentItem extends Model
{
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(MemoAssessment::class, 'memo_assessment_id');
    }

    protected function casts(): array
    {
        return [
            'calculation_type' => PenaltyCalculationType::class,
            'base_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'cap_applicable' => 'boolean',
            'calculation_inputs' => 'array',
            'suggested_amount' => 'decimal:2',
            'proposed_amount' => 'decimal:2',
        ];
    }
}
