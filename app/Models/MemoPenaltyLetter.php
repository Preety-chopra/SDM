<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['memo_id', 'memo_assessment_id', 'revision', 'issued_at', 'objection_due_at', 'delivery_details', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'uploaded_by', 'is_current'])]
class MemoPenaltyLetter extends Model
{
    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    public function objection(): HasOne
    {
        return $this->hasOne(MemoObjection::class, 'memo_penalty_letter_id');
    }

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'objection_due_at' => 'date', 'is_current' => 'boolean'];
    }
}
