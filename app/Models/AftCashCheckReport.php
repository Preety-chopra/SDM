<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['report_number', 'status', 'created_by', 'creator_name', 'creator_employee_number', 'period_from', 'period_to', 'current_step_id', 'submitted_at', 'approved_at'])]
class AftCashCheckReport extends Model
{
    protected $attributes = ['status' => 'draft'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AftCashCheckEntry::class, 'report_id')->orderBy('sequence');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AftCashCheckReportStep::class, 'report_id')->orderBy('sequence');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(AftCashCheckReportStep::class, 'current_step_id');
    }

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}
