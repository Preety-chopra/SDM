<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_id', 'mobile_number', 'method', 'status', 'original_name', 'proof_path', 'mime_type', 'size_bytes', 'acknowledged_at'])]
class MemoStaffAcknowledgement extends Model
{
    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }
}
