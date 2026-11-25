<?php

namespace App\Repositories\Eloquent;

use App\Enums\MemoStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

final class CmcSdmTableQuery
{
    public static function withStaffProposal(Builder $query): Builder
    {
        // Use the assignee from the assessment round even if the SDM was later reassigned.
        $proposal = $query->getConnection()->table('memo_assessments as assessment')
            ->leftJoin('memo_workflow_steps as scrutiny', function (JoinClause $join): void {
                $join->on('scrutiny.memo_id', '=', 'assessment.memo_id')
                    ->on('scrutiny.round', '=', 'assessment.round')
                    ->on('scrutiny.stage', '=', 'assessment.source_stage')
                    ->where('scrutiny.decision', 'forwarded');
            })
            ->whereColumn('assessment.memo_id', 'service_deficiency_memos.id')
            ->where('assessment.source_stage', WorkflowStepType::CmcAssessment->value)
            ->orderByDesc('assessment.version')
            ->limit(1)
            ->selectRaw('CASE WHEN (scrutiny.id IS NULL OR scrutiny.role = ?)
                AND assessment.proposed_by = COALESCE(scrutiny.assigned_to, service_deficiency_memos.cmc_staff_id)
                THEN assessment.staff_action END', [UserRole::CmcStaff->value]);

        return $query->addSelect(['cmc_staff_proposal' => $proposal]);
    }

    public static function forLiveMemos(Builder $query): Builder
    {
        // Submission is when a live SDM is raised. Creation is the fallback for legacy rows.
        $raisedAt = 'COALESCE(service_deficiency_memos.submitted_at, service_deficiency_memos.created_at)';
        $priorRaisedAt = 'COALESCE(prior_memos.submitted_at, prior_memos.created_at)';
        $live = self::priorAwardedPoints($query, 'service_deficiency_memos')
            ->whereRaw("({$priorRaisedAt}, prior_memos.id) < ({$raisedAt}, service_deficiency_memos.id)");
        $historical = self::priorImportedPoints($query, 'service_deficiency_memos')
            ->whereRaw("prior_imports.incident_date < DATE({$raisedAt})");

        return self::withCumulativePoints($query, $live, $historical)
            ->with(['deficiencies:id,memo_id,type', 'latestAssessment']);
    }

    public static function forImportedMemos(Builder $query): Builder
    {
        $live = self::priorAwardedPoints($query, 'historical_sdms')
            ->whereRaw('DATE(COALESCE(prior_memos.submitted_at, prior_memos.created_at)) < historical_sdms.incident_date');
        $historical = self::priorImportedPoints($query, 'historical_sdms')
            ->whereRaw('(prior_imports.incident_date, prior_imports.id) < (historical_sdms.incident_date, historical_sdms.id)');

        return self::withCumulativePoints($query, $live, $historical);
    }

    private static function priorAwardedPoints(Builder $query, string $table): QueryBuilder
    {
        return $query->getConnection()->table('service_deficiency_memos as prior_memos')
            ->join('memo_assessments as awarded', 'awarded.memo_id', '=', 'prior_memos.id')
            ->whereColumn('prior_memos.contractor_id', $table.'.contractor_id')
            ->whereColumn('prior_memos.employee_number', $table.'.employee_number')
            ->whereNull('prior_memos.deleted_at')
            ->whereNotIn('prior_memos.status', [MemoStatus::Draft->value, MemoStatus::Cancelled->value])
            ->where('awarded.status', 'approved')
            ->where('awarded.version', '=', fn (QueryBuilder $versions) => $versions
                ->from('memo_assessments')
                ->selectRaw('MAX(version)')
                ->whereColumn('memo_id', 'prior_memos.id')
                ->where('status', 'approved'))
            ->selectRaw('SUM(awarded.negative_points)');
    }

    private static function priorImportedPoints(Builder $query, string $table): QueryBuilder
    {
        return $query->getConnection()->table('historical_sdms as prior_imports')
            ->whereColumn('prior_imports.contractor_id', $table.'.contractor_id')
            ->whereColumn('prior_imports.employee_number', $table.'.employee_number')
            ->selectRaw('SUM(prior_imports.negative_points)');
    }

    private static function withCumulativePoints(Builder $query, QueryBuilder $live, QueryBuilder $historical): Builder
    {
        return $query->selectRaw(
            "COALESCE(({$live->toSql()}), 0) + COALESCE(({$historical->toSql()}), 0) AS cumulative_negative_points",
            [...$live->getBindings(), ...$historical->getBindings()],
        );
    }
}
