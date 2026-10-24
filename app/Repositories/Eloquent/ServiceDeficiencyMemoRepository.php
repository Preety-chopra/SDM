<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Enums\MemoStatus;
use App\Enums\StationManagerMemoStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\SdmSerialSequence;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ServiceDeficiencyMemoRepository implements ServiceDeficiencyMemoRepositoryInterface
{
    /** @param array<string, int|string> $filters */
    public function paginateFor(User $user, array $filters = [], ?WorkflowStage $pendingStage = null, ?string $category = null): LengthAwarePaginator
    {
        $isStationManager = $user->hasRole(UserRole::StationManager->value);
        $statusFilter = $filters['status'] ?? null;
        $relations = [
            'currentWorkflowStep:id,stage,assigned_to,assigned_name,decision',
            'deficiencies:id,memo_id,type',
            'latestActedApproval',
            'latestActedWorkflowStep',
            'latestRevertedWorkflowStep' => fn ($query) => $query->select(['memo_workflow_steps.id', 'memo_workflow_steps.memo_id', 'memo_workflow_steps.remarks', 'memo_workflow_steps.acted_at']),
            'cmcStaff:id,name',
            'cancellationApproval' => fn ($query) => $query->select(['memo_approvals.id', 'memo_approvals.memo_id', 'memo_approvals.role', 'memo_approvals.decision', 'memo_approvals.acted_at']),
            'cancellationWorkflowStep' => fn ($query) => $query->select(['memo_workflow_steps.id', 'memo_workflow_steps.memo_id', 'memo_workflow_steps.stage', 'memo_workflow_steps.decision', 'memo_workflow_steps.acted_at']),
        ];

        if ($isStationManager) {
            $relations = [
                ...$relations,
                'stationManagerApproval' => fn ($query) => $query->select(['memo_approvals.id', 'memo_approvals.memo_id', 'memo_approvals.decision', 'memo_approvals.remarks']),
                'stationManagerWorkflowStep' => fn ($query) => $query->select(['memo_workflow_steps.id', 'memo_workflow_steps.memo_id', 'memo_workflow_steps.decision', 'memo_workflow_steps.remarks']),
                'lineManagerApproval' => fn ($query) => $query->select(['memo_approvals.id', 'memo_approvals.memo_id', 'memo_approvals.assigned_name', 'memo_approvals.assigned_employee_number']),
                'lineReviewWorkflowStep' => fn ($query) => $query->select(['memo_workflow_steps.id', 'memo_workflow_steps.memo_id', 'memo_workflow_steps.assigned_name', 'memo_workflow_steps.assigned_employee_number']),
            ];
        }

        return ServiceDeficiencyMemo::query()
            ->select(['id', 'service_type', 'serial_number', 'is_urgent', 'station_name', 'line_code', 'line_name', 'incident_date', 'status', 'cancellation_type', 'workflow_version', 'current_stage', 'current_workflow_step_id', 'cmc_staff_id', 'created_by', 'submitted_at', 'created_at', 'updated_at'])
            ->with($relations)
            ->where(function (Builder $query) use ($user): void {
                $query->where('status', '!=', MemoStatus::Draft->value)
                    ->orWhere('created_by', $user->id);
            })
            ->when($filters['service_type'] ?? null, fn ($query, $serviceType) => $query->where('service_type', $serviceType))
            ->when($filters['station_id'] ?? null, fn ($query, $stationId) => $query->where('station_id', $stationId))
            ->when($filters['line_code'] ?? null, fn ($query, $lineCode) => $query->where('line_code', $lineCode))
            ->when($filters['incident_date'] ?? null, fn ($query, $incidentDate) => $query->whereDate('incident_date', $incidentDate))
            ->when($filters['incident_date_from'] ?? null, fn ($query, $incidentDateFrom) => $query->whereDate('incident_date', '>=', $incidentDateFrom))
            ->when($filters['incident_date_to'] ?? null, fn ($query, $incidentDateTo) => $query->whereDate('incident_date', '<=', $incidentDateTo))
            ->when(! $isStationManager && $statusFilter, fn (Builder $query) => $query->where('status', $statusFilter))
            ->when($isStationManager && $statusFilter, fn (Builder $query) => $this->scopeToStationManagerStatus($query, $user, StationManagerMemoStatus::from($statusFilter)))
            ->when($pendingStage, fn ($query) => $query
                ->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                ->where('current_stage', $pendingStage->value)
                ->whereHas('approvals', fn ($query) => $query
                    ->where('role', $pendingStage->value)
                    ->where('decision', 'pending')))
            ->when($category, fn (Builder $query, string $category) => $this->scopeToInboxCategory($query, $user, $category))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function bulkForwardableFor(User $user, WorkflowStepType $stage, array $filters = [], array $memoIds = []): Collection
    {
        return ServiceDeficiencyMemo::query()
            ->when($memoIds !== [], fn (Builder $query) => $query->whereKey($memoIds))
            ->when($filters['service_type'] ?? null, fn (Builder $query, string $serviceType) => $query->where('service_type', $serviceType))
            ->when($filters['station_id'] ?? null, fn (Builder $query, int|string $stationId) => $query->where('station_id', $stationId))
            ->when($filters['line_code'] ?? null, fn (Builder $query, string $lineCode) => $query->where('line_code', $lineCode))
            ->when($filters['incident_date'] ?? null, fn (Builder $query, string $incidentDate) => $query->whereDate('incident_date', $incidentDate))
            ->when($filters['incident_date_from'] ?? null, fn (Builder $query, string $incidentDateFrom) => $query->whereDate('incident_date', '>=', $incidentDateFrom))
            ->when($filters['incident_date_to'] ?? null, fn (Builder $query, string $incidentDateTo) => $query->whereDate('incident_date', '<=', $incidentDateTo))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->where('workflow_version', '>=', 2)
            ->whereHas('currentWorkflowStep', fn (Builder $query) => $query
                ->where('stage', $stage->value)
                ->where('assigned_to', $user->id)
                ->where('decision', 'pending'))
            ->orderBy('id')
            ->get();
    }

    public function bulkCmcActionableFor(User $user, WorkflowStepType $stage, array $memoIds): Collection
    {
        return ServiceDeficiencyMemo::query()
            ->whereKey($memoIds)
            ->where('workflow_version', '>=', 2)
            ->whereHas('currentWorkflowStep', fn (Builder $query) => $query
                ->where('stage', $stage->value)
                ->where('assigned_to', $user->id)
                ->where('decision', 'pending'))
            ->orderBy('id')
            ->get();
    }

    private function scopeToStationManagerStatus(Builder $query, User $user, StationManagerMemoStatus $status): Builder
    {
        if ($status === StationManagerMemoStatus::DraftsOwn) {
            return $query
                ->where('status', MemoStatus::Draft->value)
                ->where('created_by', $user->id);
        }

        $this->scopeAssignedToStationManager($query, $user);

        return match ($status) {
            StationManagerMemoStatus::SubmittedToMe => $query,
            StationManagerMemoStatus::PendingAtStationManager => $query->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('workflow_version', '<', 2)
                        ->where('current_stage', WorkflowStage::StationManager->value)
                        ->whereHas('approvals', fn (Builder $query) => $query
                            ->where('role', WorkflowStage::StationManager->value)
                            ->where('assigned_to', $user->id)
                            ->where('decision', 'pending'));
                })->orWhere(function (Builder $query) use ($user): void {
                    $query->where('workflow_version', '>=', 2)
                        ->whereHas('currentWorkflowStep', fn (Builder $query) => $query
                            ->where('stage', WorkflowStepType::StationManager->value)
                            ->where('assigned_to', $user->id)
                            ->where('decision', 'pending'));
                });
            }),
            StationManagerMemoStatus::SubmittedToAssistantManager => $query->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('workflow_version', '<', 2)
                        ->where('current_stage', WorkflowStage::LineManager->value)
                        ->whereHas('approvals', fn (Builder $query) => $query
                            ->where('role', WorkflowStage::LineManager->value)
                            ->where('decision', 'pending'));
                })->orWhere(function (Builder $query): void {
                    $query->where('workflow_version', '>=', 2)
                        ->whereHas('currentWorkflowStep', fn (Builder $query) => $query
                            ->where('stage', WorkflowStepType::LineReview->value)
                            ->where('decision', 'pending'));
                });
            }),
            StationManagerMemoStatus::ProcessedByAssistantManager => $query
                ->where('status', '!=', MemoStatus::Cancelled->value)
                ->where(function (Builder $query): void {
                    $query->where(function (Builder $query): void {
                        $query->where('workflow_version', '<', 2)
                            ->whereHas('approvals', fn (Builder $query) => $query
                                ->where('role', WorkflowStage::LineManager->value)
                                ->where('decision', 'approved'));
                    })->orWhere(function (Builder $query): void {
                        $query->where('workflow_version', '>=', 2)
                            ->whereHas('workflowSteps', fn (Builder $query) => $query
                                ->where('stage', WorkflowStepType::LineReview->value)
                                ->where('decision', 'forwarded'));
                    });
                }),
            StationManagerMemoStatus::CancelledByAssistantManager => $query
                ->where('status', MemoStatus::Cancelled->value)
                ->where(function (Builder $query): void {
                    $query->where(function (Builder $query): void {
                        $query->where('workflow_version', '<', 2)
                            ->whereHas('approvals', fn (Builder $query) => $query
                                ->where('role', WorkflowStage::LineManager->value)
                                ->where('decision', 'rejected'));
                    })->orWhere(function (Builder $query): void {
                        $query->where('workflow_version', '>=', 2)
                            ->whereHas('workflowSteps', fn (Builder $query) => $query
                                ->where('stage', WorkflowStepType::LineReview->value)
                                ->where('decision', 'cancelled'));
                    });
                }),
            StationManagerMemoStatus::DraftsOwn => $query,
        };
    }

    private function scopeAssignedToStationManager(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where(function (Builder $query) use ($user): void {
                $query->where('workflow_version', '<', 2)
                    ->whereHas('approvals', fn (Builder $query) => $query
                        ->where('role', WorkflowStage::StationManager->value)
                        ->where('assigned_to', $user->id));
            })->orWhere(function (Builder $query) use ($user): void {
                $query->where('workflow_version', '>=', 2)
                    ->whereHas('workflowSteps', fn (Builder $query) => $query
                        ->where('stage', WorkflowStepType::StationManager->value)
                        ->where('assigned_to', $user->id));
            });
        });
    }

    public function recentSubmittedBy(User $user, int $limit = 10): Collection
    {
        return ServiceDeficiencyMemo::query()
            ->select(['id', 'service_type', 'serial_number', 'is_urgent', 'status', 'workflow_version', 'created_by', 'submitted_at'])
            ->whereBelongsTo($user, 'creator')
            ->whereNotNull('submitted_at')
            ->with([
                'deficiencies:id,memo_id,type',
                'stationManagerApproval' => fn ($query) => $query->select([
                    'memo_approvals.id',
                    'memo_approvals.memo_id',
                    'memo_approvals.assigned_name',
                    'memo_approvals.assigned_employee_number',
                ]),
                'stationManagerWorkflowStep' => fn ($query) => $query->select([
                    'memo_workflow_steps.id',
                    'memo_workflow_steps.memo_id',
                    'memo_workflow_steps.assigned_name',
                    'memo_workflow_steps.assigned_employee_number',
                ]),
            ])
            ->latest('submitted_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function paginateDraftsBy(User $user): LengthAwarePaginator
    {
        return ServiceDeficiencyMemo::query()
            ->select(['id', 'service_type', 'serial_number', 'is_urgent', 'status', 'created_by', 'updated_at'])
            ->whereBelongsTo($user, 'creator')
            ->where('status', MemoStatus::Draft->value)
            ->with('deficiencies:id,memo_id,type')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(15);
    }

    private function scopeToInboxCategory(Builder $query, User $user, string $category): Builder
    {
        $assignmentSource = $category === 'transferred' ? 'handover' : 'workflow';

        return $query->where(function (Builder $query) use ($user, $assignmentSource): void {
            $query->where(function (Builder $query) use ($user, $assignmentSource): void {
                $query->where('workflow_version', '<', 2)
                    ->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                    ->whereHas('approvals', fn (Builder $query) => $query
                        ->where('assigned_to', $user->id)
                        ->where('decision', 'pending')
                        ->where('assignment_source', $assignmentSource)
                        ->whereColumn('memo_approvals.role', 'service_deficiency_memos.current_stage'));
            })->orWhere(function (Builder $query) use ($user, $assignmentSource): void {
                $query->where('workflow_version', '>=', 2)
                    ->whereHas('currentWorkflowStep', fn (Builder $query) => $query
                        ->where('assigned_to', $user->id)
                        ->where('decision', 'pending')
                        ->where('assignment_source', $assignmentSource));
            });
        });
    }

    public function pendingByStage(int $limit = 5): array
    {
        $pending = [];

        foreach (WorkflowStage::cases() as $stage) {
            $query = ServiceDeficiencyMemo::query()
                ->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                ->where('current_stage', $stage->value)
                ->whereHas('approvals', fn ($query) => $query
                    ->where('role', $stage->value)
                    ->where('decision', 'pending'));

            $pending[$stage->value] = [
                'memos' => (clone $query)
                    ->select(['id', 'serial_number', 'station_name', 'line_name', 'incident_date', 'current_stage', 'submitted_at'])
                    ->with(['approvals' => fn ($query) => $query
                        ->select(['id', 'memo_id', 'role', 'assigned_name'])
                        ->where('role', $stage->value)
                        ->where('decision', 'pending')])
                    ->latest('submitted_at')
                    ->latest('id')
                    ->limit($limit)
                    ->get(),
                'total' => (clone $query)->count(),
            ];
        }

        return $pending;
    }

    public function pendingActionCount(User $user): int
    {
        return ServiceDeficiencyMemo::query()
            ->where(function ($query) use ($user): void {
                $query->where(function ($query) use ($user): void {
                    $query->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                        ->whereHas('approvals', fn ($query) => $query
                            ->where('assigned_to', $user->id)
                            ->where('decision', 'pending')
                            ->whereColumn('memo_approvals.role', 'service_deficiency_memos.current_stage'));
                })->orWhereHas('currentWorkflowStep', fn ($query) => $query
                    ->where('assigned_to', $user->id)
                    ->where('decision', 'pending'));
            })
            ->count();
    }

    public function urgentPendingFor(User $user, int $limit = 10): Collection
    {
        return ServiceDeficiencyMemo::query()
            ->select(['id', 'service_type', 'serial_number', 'is_urgent', 'station_name', 'current_stage', 'current_workflow_step_id', 'submitted_at'])
            ->where('is_urgent', true)
            ->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->whereIn('status', [MemoStatus::Submitted->value, MemoStatus::UnderReview->value])
                        ->whereHas('approvals', fn (Builder $query) => $query
                            ->where('assigned_to', $user->id)
                            ->where('decision', 'pending')
                            ->whereColumn('memo_approvals.role', 'service_deficiency_memos.current_stage'));
                })->orWhereHas('currentWorkflowStep', fn (Builder $query) => $query
                    ->where('assigned_to', $user->id)
                    ->where('decision', 'pending'));
            })
            ->latest('submitted_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function nextSerialSequence(string $period): int
    {
        SdmSerialSequence::query()->insertOrIgnore([
            'period' => $period,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = SdmSerialSequence::query()
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->increment('last_number');

        return $sequence->last_number;
    }

    public function pendingByWorkflowStep(int $limit = 5): array
    {
        $pending = [];
        foreach (WorkflowStepType::cases() as $stage) {
            $query = ServiceDeficiencyMemo::query()->whereHas('currentWorkflowStep', fn ($query) => $query->where('stage', $stage->value)->where('decision', 'pending'));
            $pending[$stage->value] = [
                'memos' => (clone $query)->with('currentWorkflowStep:id,stage,assigned_name')->latest('submitted_at')->limit($limit)->get(),
                'total' => (clone $query)->count(),
            ];
        }

        return $pending;
    }

    public function create(array $attributes): ServiceDeficiencyMemo
    {
        return ServiceDeficiencyMemo::query()->create($attributes);
    }

    public function update(ServiceDeficiencyMemo $memo, array $attributes): ServiceDeficiencyMemo
    {
        $memo->update($attributes);

        return $memo->refresh();
    }

    public function forceDelete(ServiceDeficiencyMemo $memo): void
    {
        $memo->forceDelete();
    }
}
