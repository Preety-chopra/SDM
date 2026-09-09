<?php

namespace App\Models;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['service_type', 'serial_number', 'station_id', 'station_name', 'line_name', 'line_code', 'contractor_id', 'service_contract_id', 'cmc_staff_id', 'contractor_name', 'staff_name', 'employee_number', 'incident_date', 'incident_time', 'status', 'workflow_version', 'current_stage', 'current_workflow_step_id', 'created_by', 'updated_by', 'submitted_at'])]
class ServiceDeficiencyMemo extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['status' => MemoStatus::Draft->value, 'workflow_version' => 1];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function serviceContract(): BelongsTo
    {
        return $this->belongsTo(ServiceContract::class);
    }

    public function cmcStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cmc_staff_id');
    }

    public function currentWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(MemoWorkflowStep::class, 'current_workflow_step_id');
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(MemoWorkflowStep::class, 'memo_id')->orderBy('sequence');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(MemoAssessment::class, 'memo_id')->orderBy('version');
    }

    public function staffAcknowledgement(): HasOne
    {
        return $this->hasOne(MemoStaffAcknowledgement::class, 'memo_id');
    }

    public function penaltyLetters(): HasMany
    {
        return $this->hasMany(MemoPenaltyLetter::class, 'memo_id')->orderBy('revision');
    }

    public function currentPenaltyLetter(): HasOne
    {
        return $this->hasOne(MemoPenaltyLetter::class, 'memo_id')->where('is_current', true)->latestOfMany();
    }

    public function objections(): HasMany
    {
        return $this->hasMany(MemoObjection::class, 'memo_id');
    }

    public function deduction(): HasOne
    {
        return $this->hasOne(MemoDeduction::class, 'memo_id');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deficiencies(): HasMany
    {
        return $this->hasMany(MemoDeficiency::class, 'memo_id');
    }

    public function publicComplaint(): HasOne
    {
        return $this->hasOne(MemoPublicComplaint::class, 'memo_id');
    }

    public function cashCheck(): HasOne
    {
        return $this->hasOne(MemoCashCheck::class, 'memo_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MemoAttachment::class, 'memo_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(MemoApproval::class, 'memo_id')
            ->whereIn('role', array_column(WorkflowStage::cases(), 'value'))
            ->orderBy('sequence');
    }

    public function cancellationApproval(): HasOne
    {
        return $this->hasOne(MemoApproval::class, 'memo_id')
            ->where('decision', 'rejected')
            ->latestOfMany('acted_at');
    }

    public function cancellationWorkflowStep(): HasOne
    {
        return $this->hasOne(MemoWorkflowStep::class, 'memo_id')
            ->where('decision', 'cancelled')
            ->latestOfMany('acted_at');
    }

    public function cancellationLabel(): ?string
    {
        if ($this->status !== MemoStatus::Cancelled) {
            return null;
        }

        $stage = $this->workflow_version >= 2
            ? $this->cancellationWorkflowStep?->stage
            : $this->cancellationApproval?->role;

        return match ($stage) {
            WorkflowStage::StationManager, WorkflowStepType::StationManager => 'Cancelled by SM',
            WorkflowStage::LineManager, WorkflowStepType::LineReview => 'Cancelled by AM/Line',
            default => $stage ? 'Cancelled by '.$stage->label() : 'Cancelled',
        };
    }

    protected function casts(): array
    {
        return [
            'service_type' => SdmType::class,
            'status' => MemoStatus::class,
            'current_stage' => WorkflowStage::class,
            'incident_date' => 'date',
            'submitted_at' => 'datetime',
            'workflow_version' => 'integer',
        ];
    }
}
