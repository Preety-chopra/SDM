<?php

namespace App\Models;

use App\Enums\DeficiencyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['memo_id', 'type', 'remarks', 'overcharge_amount'])]
class MemoDeficiency extends Model
{
    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    public function lateReporting(): HasOne
    {
        return $this->hasOne(MemoLateReportingDetail::class, 'memo_deficiency_id');
    }

    protected function casts(): array
    {
        return ['type' => DeficiencyType::class, 'overcharge_amount' => 'decimal:2'];
    }
}
