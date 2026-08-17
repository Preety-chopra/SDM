<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_id', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'uploaded_by'])]
class MemoAttachment extends Model
{
    public function memo(): BelongsTo { return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
