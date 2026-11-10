<?php

namespace App\Services;

use App\Contracts\Repositories\AftCashCheckReportRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\AftCashCheckReport;
use App\Models\AftCashCheckReportStep;
use App\Models\User;
use App\Support\CashCheckAmount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AftCashCheckReportService
{
    private const array INPUT_FIELDS = ['check_date', 'station_id', 'check_type', 'tom_number', 'check_time', 'operator_name', 'employee_number', 'employer', 'eos_number', 'cash_as_per_eos', 'actual_cash', 'hidden_amount', 'cash_deposit_at_station'];

    public function __construct(
        private AftCashCheckReportRepositoryInterface $reports,
        private UserRepositoryInterface $users,
        private AuditService $audit,
    ) {}

    public function paginateFor(User $user, bool $pending = false): LengthAwarePaginator
    {
        return $this->reports->paginateFor($user, $pending);
    }

    public function pendingCountFor(User $user): int
    {
        return $this->reports->pendingCountFor($user);
    }

    public function save(?AftCashCheckReport $report, User $actor, array $data): AftCashCheckReport
    {
        return DB::transaction(function () use ($report, $actor, $data): AftCashCheckReport {
            $submitting = $data['action'] === 'submit';
            $created = $report === null;
            if ($report !== null) {
                $report = $this->reports->findForUpdate($report->id);
                Gate::forUser($actor)->authorize($submitting ? 'submit' : 'update', $report);
            } else {
                Gate::forUser($actor)->authorize('create', AftCashCheckReport::class);
            }
            $before = $report ? $this->auditValues($report) : [];
            $rows = array_values($data['entries']);
            $stations = $this->reports->stationsForUpdate(array_filter(array_column($rows, 'station_id')));
            $report = $this->reports->save($report, $created ? [
                'created_by' => $actor->id, 'creator_name' => $actor->name, 'creator_employee_number' => $actor->employee_number,
            ] : []);
            if ($report->report_number === null) {
                $this->reports->save($report, ['report_number' => 'AFT-CASH-'.str_pad((string) $report->id, 6, '0', STR_PAD_LEFT)]);
            }
            $report->entries()->delete();
            foreach ($rows as $index => $row) {
                $station = $stations->get($row['station_id'] ?? null);
                if (filled($row['station_id'] ?? null) && (! $station?->is_active || ! $station?->metroLine?->is_active)) {
                    throw ValidationException::withMessages(['entries.'.$index.'.station_id' => 'Choose an active station on an active line.']);
                }
                $difference = CashCheckAmount::difference(
                    isset($row['actual_cash']) ? (string) $row['actual_cash'] : null,
                    isset($row['cash_as_per_eos']) ? (string) $row['cash_as_per_eos'] : null,
                );
                $report->entries()->create([
                    ...Arr::only($row, self::INPUT_FIELDS),
                    'sequence' => $index + 1,
                    'station_code' => $station?->code, 'station_name' => $station?->name, 'line_code' => $station?->metroLine?->code,
                    'difference_amount' => $difference,
                    'cash_status' => $difference === null ? null : ($difference === '0.00' ? 'OK' : (str_starts_with($difference, '-') ? 'SHORT' : 'EXCESS')),
                ]);
            }
            $dates = collect($rows)->pluck('check_date')->filter()->sort()->values();
            $this->reports->save($report, ['period_from' => $dates->first(), 'period_to' => $dates->last()]);
            if ($submitting) {
                $this->createRoute($report);
            }
            $this->audit->record($report, $actor, 'aft_cash_check.'.($submitting ? 'submitted' : ($created ? 'created' : 'updated')), $before, $this->auditValues($report));

            return $report->refresh()->load(['entries', 'currentStep', 'steps']);
        });
    }

    private function createRoute(AftCashCheckReport $report): void
    {
        $assignments = [];
        foreach ([UserRole::DgmAft, UserRole::DeputyGeneralManager, UserRole::ManagerOperations, UserRole::CmcTeamLead] as $role) {
            $users = $this->users->enabledForRole($role->value);
            if ($users->count() !== 1) {
                throw ValidationException::withMessages(['workflow' => 'Exactly one enabled '.$role->label().' must be configured before submitting a cash-check report.']);
            }
            $assignments[$role->value] = $users->sole();
        }
        $first = null;
        foreach (WorkflowStepType::aftRoute() as $index => $stage) {
            $assignee = $assignments[$stage->role()->value] ?? null;
            $step = $report->steps()->create([
                'round' => 1, 'sequence' => $index + 1, 'stage' => $stage, 'role' => $stage->role()->value,
                'assigned_to' => $assignee?->id, 'assigned_name' => $assignee?->name,
                'assigned_employee_number' => $assignee?->employee_number,
            ]);
            $first ??= $step;
        }
        $this->reports->save($report, ['status' => 'submitted', 'submitted_at' => now(), 'current_step_id' => $first->id]);
    }

    public function act(AftCashCheckReport $report, User $actor, array $data): AftCashCheckReport
    {
        return DB::transaction(function () use ($report, $actor, $data): AftCashCheckReport {
            $report = $this->reports->findForUpdate($report->id);
            Gate::forUser($actor)->authorize('act', $report);
            $step = $report->steps()->whereKey($report->current_step_id)->lockForUpdate()->firstOrFail();
            $action = $data['action'];
            $allowed = match ($step->stage) {
                WorkflowStepType::CmcAssignment => ['assign'],
                WorkflowStepType::CmcVerification, WorkflowStepType::AmCmcToDgm => ['forward', 'return'],
                WorkflowStepType::DgmApproval => ['approve', 'return', 'cancel'],
                default => ['forward'],
            };
            if (! in_array($action, $allowed, true)) {
                throw ValidationException::withMessages(['action' => 'This action is not available at the current review stage.']);
            }
            if (in_array($action, ['return', 'cancel'], true) && blank($data['remarks'] ?? null)) {
                throw ValidationException::withMessages(['remarks' => 'Enter a reason for this action.']);
            }
            if ($action === 'assign') {
                $staff = $this->users->enabledForRole(UserRole::CmcStaff->value)->firstWhere('id', (int) $data['cmc_staff_id']);
                if ($staff === null) {
                    throw ValidationException::withMessages(['cmc_staff_id' => 'Choose an enabled CMC Staff user.']);
                }
                $report->steps()->where('stage', WorkflowStepType::CmcAssessment->value)->where('decision', 'pending')->update([
                    'assigned_to' => $staff->id, 'assigned_name' => $staff->name, 'assigned_employee_number' => $staff->employee_number,
                ]);
            }
            if ($step->stage === WorkflowStepType::CmcAssessment) {
                $this->saveReviews($report, $actor, $data['reviews'] ?? []);
            }
            $step->update([
                'decision' => match ($action) {
                    'approve' => 'approved', 'cancel' => 'cancelled', 'return' => 'returned', default => 'forwarded'
                },
                'acted_by' => $actor->id, 'actor_name' => $actor->name, 'actor_employee_number' => $actor->employee_number,
                'remarks' => $data['remarks'] ?? null, 'acted_at' => now(),
            ]);
            if ($action === 'approve' || $action === 'cancel') {
                $this->reports->save($report, ['status' => $action === 'approve' ? 'approved' : 'cancelled', 'current_step_id' => null, 'approved_at' => $action === 'approve' ? now() : null]);
            } elseif ($action === 'return') {
                $this->returnForReview($report, $step);
            } else {
                $next = $report->steps()->where('sequence', '>', $step->sequence)->where('decision', 'pending')->firstOrFail();
                $this->reports->save($report, ['status' => 'under_review', 'current_step_id' => $next->id]);
            }
            $this->audit->record($report, $actor, 'aft_cash_check.workflow.'.$action, [], [
                'step_id' => $step->id, 'stage' => $step->stage->value, 'status' => $report->status,
                ...($action === 'assign' ? ['cmc_staff_id' => (int) $data['cmc_staff_id']] : []),
            ]);

            return $report->refresh()->load(['entries', 'currentStep', 'steps']);
        });
    }

    private function saveReviews(AftCashCheckReport $report, User $actor, array $reviews): void
    {
        $entries = $report->entries()->lockForUpdate()->get()->keyBy('id');
        $ids = array_map('intval', array_column($reviews, 'entry_id'));
        if (count($ids) !== $entries->count() || count(array_unique($ids)) !== count($ids) || array_diff($ids, $entries->keys()->all()) !== []) {
            throw ValidationException::withMessages(['reviews' => 'Complete the CMC review for every cash-check row in this report.']);
        }
        foreach ($reviews as $review) {
            $entry = $entries->get((int) $review['entry_id']);
            $fields = ['remarks', 'cumulative_negative_points', 'penalty_amount'];
            $before = $entry->only($fields);
            $entry->update(Arr::only($review, $fields));
            $this->audit->record($entry, $actor, 'aft_cash_check.entry.reviewed', $before, $entry->only($fields));
        }
    }

    private function returnForReview(AftCashCheckReport $report, AftCashCheckReportStep $step): void
    {
        $stages = [WorkflowStepType::CmcAssessment, WorkflowStepType::CmcVerification, WorkflowStepType::AmCmcToDgm, WorkflowStepType::DgmApproval];
        if ($step->stage === WorkflowStepType::AmCmcToDgm) {
            array_shift($stages);
        }
        $round = (int) $report->steps()->max('round') + 1;
        $sequence = (int) $report->steps()->max('sequence');
        $report->steps()->where('decision', 'pending')->update(['decision' => 'superseded']);
        $first = null;
        foreach ($stages as $stage) {
            $prior = $report->steps()->where('stage', $stage->value)->reorder('sequence', 'desc')->firstOrFail();
            $newStep = $report->steps()->create([
                ...$prior->only(['role', 'assigned_to', 'assigned_name', 'assigned_employee_number']),
                'stage' => $stage, 'round' => $round, 'sequence' => ++$sequence,
            ]);
            $first ??= $newStep;
        }
        $this->reports->save($report, ['status' => 'under_review', 'current_step_id' => $first->id]);
    }

    private function auditValues(AftCashCheckReport $report): array
    {
        return [
            ...$report->only(['report_number', 'status', 'period_from', 'period_to']),
            'entries' => $report->entries()->get()->map(fn ($entry) => $entry->only([
                ...self::INPUT_FIELDS, 'sequence', 'station_code', 'station_name', 'line_code', 'difference_amount', 'cash_status',
            ]))->all(),
        ];
    }
}
