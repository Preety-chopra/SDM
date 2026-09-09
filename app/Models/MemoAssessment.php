<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['memo_id', 'round', 'version', 'source_stage', 'proposed_by', 'negative_points', 'staff_action', 'bill_month', 'monthly_bill_value', 'proposed_total', 'cap_percentage', 'cap_warning', 'cap_acknowledged', 'administrative_review_flag', 'status', 'approved_at'])]
class MemoAssessment extends Model
{
    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MemoAssessmentItem::class);
    }

    protected function casts(): array
    {
        return [
            'bill_month' => 'date',
            'monthly_bill_value' => 'decimal:2',
            'proposed_total' => 'decimal:2',
            'cap_percentage' => 'decimal:3',
            'cap_warning' => 'boolean',
            'cap_acknowledged' => 'boolean',
            'administrative_review_flag' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }
}
