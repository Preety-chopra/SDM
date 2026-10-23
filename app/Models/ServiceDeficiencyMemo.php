<?php

namespace App\Models;

use App\Enums\MemoCancellationType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['service_type', 'serial_number', 'is_urgent', 'station_id', 'station_name', 'line_name', 'line_code', 'contractor_id', 'service_contract_id', 'cmc_staff_id', 'contractor_name', 'staff_name', 'employee_number', 'incident_date', 'incident_time', 'station_record_brief_remarks', 'status', 'cancellation_type', 'workflow_version', 'current_stage', 'current_workflow_step_id', 'created_by', 'updated_by', 'submitted_at'])]
class ServiceDeficiencyMemo extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['status' => MemoStatus::Draft->value, 'workflow_version' => 1, 'is_urgent' => false];

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

    public function stationManagerWorkflowStep(): HasOne
    {
        return $this->hasOne(MemoWorkflowStep::class, 'memo_id')
            ->where('stage', WorkflowStepType::StationManager->value)
            ->oldestOfMany();
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

    public function stationManagerApproval(): HasOne
    {
        return $this->hasOne(MemoApproval::class, 'memo_id')
            ->where('role', WorkflowStage::StationManager->value)
            ->oldestOfMany();
    }

    public function lineManagerApproval(): HasOne
    {
        return $this->hasOne(MemoApproval::class, 'memo_id')
            ->where('role', WorkflowStage::LineManager->value);
    }

    public function lineReviewWorkflowStep(): HasOne
    {
        return $this->hasOne(MemoWorkflowStep::class, 'memo_id')
            ->where('stage', WorkflowStepType::LineReview->value)
            ->oldest('sequence');
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

    public function latestRevertedWorkflowStep(): HasOne
    {
        return $this->hasOne(MemoWorkflowStep::class, 'memo_id')
            ->where('decision', 'returned')
            ->latestOfMany('acted_at');
    }

    public function latestActedApproval(): HasOne
    {
        return $this->hasOne(MemoApproval::class, 'memo_id')
            ->whereNotNull('acted_at')
            ->latestOfMany('acted_at');
    }

    public function latestActedWorkflowStep(): HasOne
    {
        return $this->hasOne(MemoWorkflowStep::class, 'memo_id')
            ->whereNotNull('acted_at')
            ->latestOfMany('acted_at');
    }

    public function statusUpdatedAt(): ?DateTimeInterface
    {
        if ($this->status === MemoStatus::Draft) {
            return $this->updated_at;
        }

        $latestActionAt = $this->workflow_version >= 2
            ? $this->latestActedWorkflowStep?->acted_at
            : $this->latestActedApproval?->acted_at;

        return $latestActionAt ?? $this->submitted_at ?? $this->updated_at;
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
            WorkflowStage::LineManager, WorkflowStepType::LineReview => $this->lineCancellationLabel(),
            default => $stage ? 'Cancelled by '.$stage->label() : 'Cancelled',
        };
    }

    public function stationManagerStatusLabel(): string
    {
        if ($this->status === MemoStatus::Draft) {
            return 'Draft';
        }

        if ($this->status === MemoStatus::Cancelled) {
            $stage = $this->workflow_version >= 2
                ? $this->cancellationWorkflowStep?->stage
                : $this->cancellationApproval?->role;

            return match ($stage) {
                WorkflowStage::StationManager, WorkflowStepType::StationManager => 'Cancelled by SM',
                WorkflowStage::LineManager, WorkflowStepType::LineReview => $this->lineCancellationLabel(),
                default => $stage ? 'Forwarded' : 'Cancelled',
            };
        }

        $stage = $this->workflow_version >= 2
            ? $this->currentWorkflowStep?->stage
            : $this->current_stage;

        return match ($stage) {
            WorkflowStage::StationManager, WorkflowStepType::StationManager,
            WorkflowStage::LineManager, WorkflowStepType::LineReview => 'With '.$stage->label(),
            default => 'Forwarded By AM/Manager (Line)',
        };
    }

    public function stationManagerListStatusLabel(): string
    {
        if ($this->status === MemoStatus::Draft) {
            return 'Draft';
        }

        if ($this->status === MemoStatus::Cancelled) {
            $stage = $this->workflow_version >= 2
                ? $this->cancellationWorkflowStep?->stage
                : $this->cancellationApproval?->role;

            return match ($stage) {
                WorkflowStage::LineManager, WorkflowStepType::LineReview => $this->lineCancellationLabel(),
                WorkflowStage::StationManager, WorkflowStepType::StationManager => 'Cancelled by SM',
                default => 'Cancelled',
            };
        }

        $stage = $this->workflow_version >= 2
            ? $this->currentWorkflowStep?->stage
            : $this->current_stage;

        if (in_array($stage, [WorkflowStage::StationManager, WorkflowStepType::StationManager], true)) {
            return 'Pending (at SM level)';
        }

        $stationManagerAction = $this->workflow_version >= 2
            ? $this->stationManagerWorkflowStep
            : $this->stationManagerApproval;

        if (in_array($stage, [WorkflowStage::LineManager, WorkflowStepType::LineReview], true)) {
            return $stationManagerAction?->remarks === 'Cancellation requested by Station Manager.'
                ? 'Cancellation requested'
                : 'Forwarded to AM';
        }

        return 'Processed by AM';
    }

    public function forwardedToLineManagerLabel(): string
    {
        $lineManager = $this->workflow_version >= 2
            ? $this->lineReviewWorkflowStep
            : $this->lineManagerApproval;
        $name = $lineManager?->assigned_name;
        $employeeNumber = $lineManager?->assigned_employee_number;

        if (filled($name) && filled($employeeNumber)) {
            return "{$name} ({$employeeNumber})";
        }

        return $name ?: ($employeeNumber ?: '—');
    }

    public function stationControllerStatusLabel(): string
    {
        return in_array($this->status, [MemoStatus::Submitted, MemoStatus::AwaitingStationManager], true)
            ? 'Pending'
            : 'Processed by SM';
    }

    private function lineCancellationLabel(): string
    {
        return $this->cancellation_type === MemoCancellationType::AfterCounselling
            ? 'Cancelled after counselling by AM/Line'
            : 'Cancelled by AM/Line';
    }

    protected function casts(): array
    {
        return [
            'service_type' => SdmType::class,
            'status' => MemoStatus::class,
            'cancellation_type' => MemoCancellationType::class,
            'is_urgent' => 'boolean',
            'current_stage' => WorkflowStage::class,
            'incident_date' => 'date',
            'submitted_at' => 'datetime',
            'workflow_version' => 'integer',
        ];
    }
}
