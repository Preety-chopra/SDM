<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_id', 'complaint_serial_number', 'station_id', 'station_name', 'remarks'])]
class MemoPublicComplaint extends Model
{
    public function memo(): BelongsTo { return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id'); }
    public function station(): BelongsTo { return $this->belongsTo(Station::class); }
}
