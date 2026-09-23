<?php

namespace App\Models;

use App\Enums\WorkflowStepType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_id', 'round', 'sequence', 'stage', 'role', 'assigned_to', 'assigned_name', 'assigned_employee_number', 'assignment_source', 'decision', 'acted_by', 'actor_name', 'actor_employee_number', 'remarks', 'metadata', 'acted_at'])]
class MemoWorkflowStep extends Model
{
    protected $attributes = ['assignment_source' => 'workflow'];

    public function memo(): BelongsTo
    {
        return $this->belongsTo(ServiceDeficiencyMemo::class, 'memo_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected function casts(): array
    {
        return ['stage' => WorkflowStepType::class, 'metadata' => 'array', 'acted_at' => 'datetime'];
    }
}
