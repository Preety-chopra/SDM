<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_id', 'tom_ccc_number', 'end_of_shift_receipt_number', 'checked_at', 'difference_amount', 'hidden_cash_amount', 'operator_name', 'checking_official_name', 'checking_official_designation', 'checking_official_employee_number', 'remarks'])]
class MemoCashCheck extends Model
{
    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'difference_amount' => 'decimal:2', 'hidden_cash_amount' => 'decimal:2'];
    }
}
