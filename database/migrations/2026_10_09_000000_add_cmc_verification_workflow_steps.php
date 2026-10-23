<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $assessmentSteps = DB::table('memo_workflow_steps as assessment')
                ->join('service_deficiency_memos as memo', 'memo.id', '=', 'assessment.memo_id')
                ->where('memo.workflow_version', '>=', 2)
                ->where('assessment.stage', 'cmc_assessment')
                ->whereNotExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('memo_workflow_steps as verification')
                    ->whereColumn('verification.memo_id', 'assessment.memo_id')
                    ->whereColumn('verification.round', 'assessment.round')
                    ->where('verification.stage', 'cmc_verification'))
                ->select(['assessment.id', 'assessment.memo_id', 'assessment.round', 'assessment.sequence', 'assessment.decision'])
                ->orderBy('assessment.memo_id')
                ->orderByDesc('assessment.sequence')
                ->get();

            foreach ($assessmentSteps as $assessment) {
                $teamLead = DB::table('memo_workflow_steps')
                    ->where('memo_id', $assessment->memo_id)
                    ->where('stage', 'cmc_assignment')
                    ->orderBy('sequence')
                    ->first(['assigned_to', 'assigned_name', 'assigned_employee_number']);

                if (! $teamLead) {
                    continue;
                }

                DB::table('memo_workflow_steps')
                    ->where('memo_id', $assessment->memo_id)
                    ->where('round', $assessment->round)
                    ->where('sequence', '>', $assessment->sequence)
                    ->orderByDesc('sequence')
                    ->get(['id', 'sequence'])
                    ->each(fn (object $step) => DB::table('memo_workflow_steps')
                        ->where('id', $step->id)
                        ->update(['sequence' => $step->sequence + 1]));

                $isPending = $assessment->decision === 'pending';
                DB::table('memo_workflow_steps')->insert([
                    'memo_id' => $assessment->memo_id,
                    'round' => $assessment->round,
                    'sequence' => $assessment->sequence + 1,
                    'stage' => 'cmc_verification',
                    'role' => 'cmc_team_lead',
                    'assigned_to' => $teamLead->assigned_to,
                    'assigned_name' => $teamLead->assigned_name,
                    'assigned_employee_number' => $teamLead->assigned_employee_number,
                    'assignment_source' => 'workflow',
                    'decision' => $isPending ? 'pending' : 'forwarded',
                    'remarks' => $isPending ? null : 'CMC verification checkpoint added during workflow upgrade.',
                    'acted_at' => $isPending ? null : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $verificationSteps = DB::table('memo_workflow_steps')
                ->where('stage', 'cmc_verification')
                ->orderBy('memo_id')
                ->orderByDesc('sequence')
                ->get(['id', 'memo_id', 'round', 'sequence']);

            foreach ($verificationSteps as $verification) {
                $nextStep = DB::table('memo_workflow_steps')
                    ->where('memo_id', $verification->memo_id)
                    ->where('round', $verification->round)
                    ->where('sequence', '>', $verification->sequence)
                    ->orderBy('sequence')
                    ->first(['id']);

                DB::table('service_deficiency_memos')
                    ->where('current_workflow_step_id', $verification->id)
                    ->update([
                        'current_workflow_step_id' => $nextStep?->id,
                        'status' => $nextStep ? 'am_cmc_review' : 'cmc_scrutiny',
                    ]);

                DB::table('memo_workflow_steps')->where('id', $verification->id)->delete();
                DB::table('memo_workflow_steps')
                    ->where('memo_id', $verification->memo_id)
                    ->where('round', $verification->round)
                    ->where('sequence', '>', $verification->sequence)
                    ->orderBy('sequence')
                    ->get(['id', 'sequence'])
                    ->each(fn (object $step) => DB::table('memo_workflow_steps')
                        ->where('id', $step->id)
                        ->update(['sequence' => $step->sequence - 1]));
            }
        });
    }
};
