<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_id', 'memo_penalty_letter_id', 'received_at', 'remarks', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'recorded_by'])]
class MemoObjection extends Model
{
    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    protected function casts(): array
    {
        return ['received_at' => 'date'];
    }
}
