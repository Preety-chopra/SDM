<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_id', 'sequence', 'check_date', 'station_id', 'station_code', 'station_name', 'line_code', 'check_type', 'tom_number', 'check_time', 'operator_name', 'employee_number', 'employer', 'eos_number', 'cash_as_per_eos', 'actual_cash', 'difference_amount', 'cash_status', 'hidden_amount', 'cash_deposit_at_station', 'remarks', 'cumulative_negative_points', 'penalty_amount'])]
class AftCashCheckEntry extends Model
{
    public function report(): BelongsTo
    {
        return $this->belongsTo(AftCashCheckReport::class, 'report_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    protected function casts(): array
    {
        return [
            'check_date' => 'date', 'cash_as_per_eos' => 'decimal:2', 'actual_cash' => 'decimal:2',
            'difference_amount' => 'decimal:2', 'hidden_amount' => 'decimal:2',
            'cash_deposit_at_station' => 'decimal:2', 'penalty_amount' => 'decimal:2',
            'cumulative_negative_points' => 'integer',
        ];
    }
}
