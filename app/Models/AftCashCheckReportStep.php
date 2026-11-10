<?php

namespace App\Models;

use App\Enums\WorkflowStepType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_id', 'sequence', 'round', 'stage', 'role', 'assigned_to', 'assigned_name', 'assigned_employee_number', 'decision', 'acted_by', 'actor_name', 'actor_employee_number', 'remarks', 'acted_at'])]
class AftCashCheckReportStep extends Model
{
    public function report(): BelongsTo
    {
        return $this->belongsTo(AftCashCheckReport::class, 'report_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected function casts(): array
    {
        return ['stage' => WorkflowStepType::class, 'acted_at' => 'datetime'];
    }
}
