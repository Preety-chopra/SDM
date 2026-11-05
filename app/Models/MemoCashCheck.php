<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function excessAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->difference_amount !== null && (float) $this->difference_amount >= 0
            ? $this->difference_amount
            : null);
    }

    protected function amountType(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->difference_amount === null
            ? null
            : ((float) $this->difference_amount < 0 ? 'short' : 'excess'));
    }

    protected function amount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->difference_amount === null ? null : ltrim($this->difference_amount, '-'));
    }

    protected function shortAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->difference_amount !== null && (float) $this->difference_amount < 0
            ? substr($this->difference_amount, 1)
            : null);
    }
}
