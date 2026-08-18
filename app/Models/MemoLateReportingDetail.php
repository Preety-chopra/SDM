<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_deficiency_id', 'booked_shift_from', 'booked_shift_to', 'actual_reporting_time'])]
class MemoLateReportingDetail extends Model
{
    public function deficiency(): BelongsTo
    {
        return $this->belongsTo(MemoDeficiency::class, 'memo_deficiency_id');
    }
}
