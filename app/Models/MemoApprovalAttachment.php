<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_approval_id', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'uploaded_by'])]
class MemoApprovalAttachment extends Model
{
    public function approval(): BelongsTo
    {
        return $this->belongsTo(MemoApproval::class, 'memo_approval_id');
    }
}
