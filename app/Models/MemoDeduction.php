<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_id', 'bill_month', 'bill_reference', 'deducted_at', 'amount', 'recorded_by'])]
class MemoDeduction extends Model
{
    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    protected function casts(): array
    {
        return ['bill_month' => 'date', 'deducted_at' => 'date', 'amount' => 'decimal:2'];
    }
}
