<?php

namespace App\Models;

use App\Enums\WorkflowStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['memo_id', 'role', 'sequence', 'assigned_to', 'assigned_name', 'assigned_employee_number', 'signer_name', 'signer_employee_number', 'decision', 'acted_by', 'acted_at', 'remarks'])]
class MemoApproval extends Model
{
    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MemoApprovalAttachment::class);
    }

    protected function casts(): array
    {
        return ['role' => WorkflowStage::class, 'acted_at' => 'datetime'];
    }
}
