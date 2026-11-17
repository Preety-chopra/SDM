<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\Line;
use App\Models\MemoWorkflowStep;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmcTeamLeadMemoTableTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $line = Line::query()->create(['code' => 'YL', 'name' => 'Yellow Line', 'is_active' => true]);
        $this->station = Station::query()->create([
            'code' => 'YL-01',
            'name' => 'Central Secretariat',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
    }

    public function test_cmc_sm_all_memos_table_has_filters_without_selection_or_row_actions(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead, 'CMC-SM-01');
        $cmcStaff = $this->user(UserRole::CmcStaff, 'CMC-STAFF-01');
        $assignmentMemo = $this->memoAtStage('CMC-TABLE-ASSIGN', $cmcSm, WorkflowStepType::CmcAssignment);
        $verificationMemo = $this->memoAtStage('CMC-TABLE-VERIFY', $cmcSm, WorkflowStepType::CmcVerification, $cmcStaff);

        $response = $this->actingAs($cmcSm)
            ->get(route('sdm-memos.index'));

        $response
            ->assertOk()
            ->assertSee('form="sdm-filter-form"', false)
            ->assertSee('@change="$el.form.requestSubmit()"', false)
            ->assertSee('name="service_type"', false)
            ->assertSee('name="line_code"', false)
            ->assertSee('aria-label="Open SDM type filter"', false)
            ->assertSee('aria-label="Open Line / station filter"', false)
            ->assertSee('aria-label="Open Incident date filter"', false)
            ->assertSee('aria-label="Open Submitted on filter"', false)
            ->assertSee('aria-label="Open Current status filter"', false)
            ->assertSee('data-column-filter="SDM type"', false)
            ->assertSee('@click.prevent.stop="openPicker($el)"', false)
            ->assertSee('x-ref="picker"', false)
            ->assertSee('aria-label="Submitted date range" class="grid gap-3"', false)
            ->assertDontSee('<tr class="border-t border-gray-200 align-top dark:border-gray-700">', false)
            ->assertDontSee('Submitted Till')
            ->assertDontSee('name="memo_ids[]"', false)
            ->assertDontSee('Select page')
            ->assertDontSee('id="bulk-cmc-form"', false)
            ->assertSee('YL')
            ->assertDontSee('Yellow Line')
            ->assertSee('aria-label="Open SDM '.$assignmentMemo->serial_number.'"', false)
            ->assertSee('href="'.route('sdm-memos.show', $assignmentMemo).'"', false)
            ->assertDontSee('>Open</a>', false)
            ->assertSee($assignmentMemo->serial_number)
            ->assertSee($verificationMemo->serial_number);

        $this->assertDoesNotMatchRegularExpression('/>\s*Serial number\s*</', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/>\s*Action\s*<\/th>/', $response->getContent());
    }

    public function test_am_line_and_cmc_workflow_roles_use_the_cmc_sm_header_filter_table(): void
    {
        $roles = [
            UserRole::AssistantManager,
            UserRole::ManagerOperations,
            UserRole::CmcStaff,
            UserRole::DeputyGeneralManager,
        ];

        foreach ($roles as $index => $role) {
            $response = $this->actingAs($this->user($role, 'HEADER-FILTER-'.$index))
                ->get(route('sdm-memos.index'));

            $response
                ->assertOk()
                ->assertSee('form="sdm-filter-form"', false)
                ->assertSee('aria-label="Open SDM type filter"', false)
                ->assertSee('aria-label="Open Line / station filter"', false)
                ->assertSee('aria-label="Open Incident date filter"', false)
                ->assertSee('aria-label="Open Submitted on filter"', false)
                ->assertSee('aria-label="Open Current status filter"', false)
                ->assertDontSee('id="service_type"', false);
        }
    }

    public function test_cmc_sm_can_filter_the_staff_assignment_queue_by_service_type(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead, 'TABS-SM');
        $otherCmcSm = $this->user(UserRole::CmcTeamLead, 'TABS-OTHER-SM');
        $firstStaff = $this->user(UserRole::CmcStaff, 'TABS-FIRST-STAFF');
        $secondStaff = $this->user(UserRole::CmcStaff, 'TABS-SECOND-STAFF');
        $disabledStaff = $this->user(UserRole::CmcStaff, 'TABS-DISABLED-STAFF');
        $disabledStaff->update(['is_enabled' => false]);

        $memosByType = collect(SdmType::cases())->mapWithKeys(function (SdmType $type) use ($cmcSm): array {
            $memo = $this->memoAtStage('TABS-ASSIGN-'.$type->value, $cmcSm, WorkflowStepType::CmcAssignment);
            $memo->update(['service_type' => $type]);

            return [$type->value => $memo->id];
        });
        $this->memoAtStage('TABS-ASSIGN-OTHER', $otherCmcSm, WorkflowStepType::CmcAssignment);
        $this->memoAtStage('TABS-ASSESS-STAFF', $cmcSm, WorkflowStepType::CmcAssessment, $firstStaff);
        $this->memoAtStage('TABS-VERIFY-ORDINARY', $cmcSm, WorkflowStepType::CmcVerification, $firstStaff);

        $this->actingAs($cmcSm)->get(route('sdm-memos.index', ['acknowledge_notifications' => 1]))
            ->assertOk()
            ->assertSeeInOrder(['All Memos', 'SDMs Pending for CMC Staff Assignment', 'SDMs Processed by CMC Staff', 'SDMs Approved by DGM/CMC'])
            ->assertDontSee('Pending Memos');

        $response = $this->get(route('sdm-memos.index', ['category' => 'pending_cmc_staff_assignment']))
            ->assertOk()
            ->assertSeeInOrder(['All types', 'CFA', 'TVS', 'W&amp;W', 'Marshal', 'BHS'], false)
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->sort()->values()->all() === $memosByType->values()->all());
        $this->assertDoesNotMatchRegularExpression('/>\s*Action\s*<\/th>/', $response->getContent());

        foreach ($memosByType as $type => $memoId) {
            $this->get(route('sdm-memos.index', ['category' => 'pending_cmc_staff_assignment', 'service_type' => $type]))
                ->assertOk()
                ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->all() === [$memoId])
                ->assertSee('name="service_type" value="'.$type.'"', false)
                ->assertSee('name="memo_ids[]"', false)
                ->assertSee('Select CMC staff')
                ->assertSee($firstStaff->employee_number)
                ->assertSee($secondStaff->employee_number)
                ->assertDontSee($disabledStaff->employee_number);
        }

        $this->actingAs($firstStaff)->from(route('sdm-memos.index'))
            ->get(route('sdm-memos.index', ['category' => 'pending_cmc_staff_assignment']))
            ->assertRedirect(route('sdm-memos.index'))
            ->assertSessionHasErrors('category');
    }

    public function test_cmc_sm_verification_tabs_show_pending_and_forwarded_sdms(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead, 'PROCESSED-SM');
        $otherCmcSm = $this->user(UserRole::CmcTeamLead, 'PROCESSED-OTHER-SM');
        $staff = $this->user(UserRole::CmcStaff, 'PROCESSED-STAFF');
        $this->memoAtStage('TABS-PENDING-ASSIGNMENT', $cmcSm, WorkflowStepType::CmcAssignment);
        $this->memoAtStage('TABS-PENDING-ASSESSMENT', $cmcSm, WorkflowStepType::CmcAssessment, $staff);
        $this->memoAtStage('TABS-VERIFY-OTHER', $otherCmcSm, WorkflowStepType::CmcVerification, $staff);
        $verification = $this->memoAtStage('TABS-VERIFY', $cmcSm, WorkflowStepType::CmcVerification, $staff);
        $directVerification = $this->memoAtStage('TABS-DIRECT', $cmcSm, WorkflowStepType::CmcVerification);
        $directVerification->workflowSteps()->where('stage', WorkflowStepType::CmcAssessment->value)
            ->update(['assigned_to' => $cmcSm->id, 'acted_by' => $cmcSm->id]);
        $managerReview = $this->memoAtStage('TABS-REVIEW-MANAGER', $cmcSm, WorkflowStepType::AmCmcToDgm, $staff);
        $dgmReview = $this->memoAtStage('TABS-REVIEW-DGM', $cmcSm, WorkflowStepType::DgmApproval, $staff);
        $approved = $this->memoAtStage('TABS-APPROVED', $cmcSm, WorkflowStepType::DgmApproval, $staff);
        $approved->currentWorkflowStep->update(['decision' => 'approved']);
        $approved->update(['status' => MemoStatus::PendingPenaltyLetter, 'current_workflow_step_id' => null]);
        $legacyApproved = $this->memoAtStage('TABS-LEGACY', $cmcSm, WorkflowStepType::DgmApproval, $staff);
        $legacyApproved->update(['workflow_version' => 1, 'status' => MemoStatus::Closed, 'current_workflow_step_id' => null]);
        $legacyApproved->approvals()->create([
            'role' => WorkflowStage::DeputyGeneralManager->value,
            'sequence' => WorkflowStage::DeputyGeneralManager->sequence(),
            'decision' => 'approved',
        ]);

        $queue = ['category' => 'processed_by_cmc_staff'];
        $this->actingAs($cmcSm);

        foreach ([$queue, [...$queue, 'verification_tab' => 'pending']] as $pendingQueue) {
            $response = $this->get(route('sdm-memos.index', $pendingQueue))
                ->assertOk()
                ->assertSeeInOrder(['Pending SDMs', 'Processed SDMs'])
                ->assertSee('href="'.e(route('sdm-memos.index', [...$queue, 'verification_tab' => 'processed'])).'"', false)
                ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->sort()->values()->all() === [$verification->id, $directVerification->id])
                ->assertSee('name="memo_ids[]"', false)
                ->assertSee('data-initial-action="forward"', false)
                ->assertSee('Forward to Manager CMC')
                ->assertSee('Revert for rechecking')
                ->assertDontSee('action="'.route('sdm-memos.workflow.store', $verification).'"', false)
                ->assertSee('href="'.route('sdm-memos.show', $verification).'"', false);

            $this->assertDoesNotMatchRegularExpression('/>\s*Action\s*<\/th>/', $response->getContent());
        }

        $this->get(route('sdm-memos.index', [...$queue, 'verification_tab' => 'processed']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->sort()->values()->all() === [$managerReview->id, $dgmReview->id, $approved->id])
            ->assertSee('name="verification_tab" value="processed"', false)
            ->assertDontSee('name="memo_ids[]"', false)
            ->assertDontSee('id="bulk-cmc-form"', false);

        $this->get(route('sdm-memos.index', ['category' => 'approved_by_dgm_cmc']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->sort()->values()->all() === [$approved->id, $legacyApproved->id]);
    }

    public function test_cmc_sm_can_bulk_assign_selected_sdms_to_cmc_staff(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead, 'CMC-SM-02');
        $cmcStaff = $this->user(UserRole::CmcStaff, 'CMC-STAFF-02');
        $first = $this->memoAtStage('CMC-BULK-ASSIGN-1', $cmcSm, WorkflowStepType::CmcAssignment);
        $second = $this->memoAtStage('CMC-BULK-ASSIGN-2', $cmcSm, WorkflowStepType::CmcAssignment);
        $queue = ['category' => 'pending_cmc_staff_assignment', 'service_type' => SdmType::Cfs->value];

        $this->actingAs($cmcSm)->post(route('sdm-memos.bulk-cmc-action'), [
            ...$queue,
            'action' => 'assign',
            'memo_ids' => [$first->id, $second->id],
            'cmc_staff_id' => $cmcStaff->id,
        ])->assertRedirect(route('sdm-memos.index', $queue))
            ->assertSessionHasNoErrors();

        foreach ([$first, $second] as $memo) {
            $memo->refresh();
            $this->assertSame($cmcStaff->id, $memo->cmc_staff_id);
            $this->assertSame(WorkflowStepType::CmcAssessment, $memo->currentWorkflowStep->stage);
            $this->assertSame($cmcStaff->id, $memo->currentWorkflowStep->assigned_to);
            $this->assertTrue($memo->auditLogs()->where('event', 'memo.workflow.assign')->where('actor_id', $cmcSm->id)->exists());
        }

        $this->get(route('sdm-memos.index', $queue))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->isEmpty());
    }

    public function test_cmc_sm_can_bulk_forward_or_revert_sdms_at_the_verification_stage(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead, 'CMC-SM-03');
        $cmcStaff = $this->user(UserRole::CmcStaff, 'CMC-STAFF-03');
        $forward = $this->memoAtStage('CMC-BULK-FORWARD', $cmcSm, WorkflowStepType::CmcVerification, $cmcStaff);
        $secondForward = $this->memoAtStage('CMC-BULK-SECOND', $cmcSm, WorkflowStepType::CmcVerification);
        $revert = $this->memoAtStage('CMC-BULK-REVERT', $cmcSm, WorkflowStepType::CmcVerification, $cmcStaff);
        $queue = ['category' => 'processed_by_cmc_staff'];

        $this->actingAs($cmcSm)->post(route('sdm-memos.bulk-cmc-action'), [
            ...$queue,
            'action' => 'forward',
            'memo_ids' => [$forward->id, $secondForward->id],
        ])->assertRedirect(route('sdm-memos.index', $queue))->assertSessionHasNoErrors();

        foreach ([$forward, $secondForward] as $memo) {
            $memo->refresh();
            $this->assertSame(WorkflowStepType::AmCmcToDgm, $memo->currentWorkflowStep->stage);
            $this->assertTrue($memo->auditLogs()->where('event', 'memo.workflow.forward')->where('actor_id', $cmcSm->id)->exists());
        }

        $this->actingAs($cmcSm)->from(route('sdm-memos.index', $queue))->post(route('sdm-memos.bulk-cmc-action'), [
            ...$queue,
            'action' => 'return',
            'memo_ids' => [$revert->id],
            'remarks' => '   ',
        ])->assertRedirect(route('sdm-memos.index', $queue))->assertSessionHasErrors('remarks');

        $this->assertSame(WorkflowStepType::CmcVerification, $revert->fresh()->currentWorkflowStep->stage);

        $this->actingAs($cmcSm)->post(route('sdm-memos.bulk-cmc-action'), [
            ...$queue,
            'action' => 'return',
            'memo_ids' => [$revert->id],
            'remarks' => 'Penalty calculation needs another check.',
        ])->assertRedirect(route('sdm-memos.index', $queue))->assertSessionHasNoErrors();

        $revert->refresh();
        $this->assertSame(WorkflowStepType::CmcAssessment, $revert->currentWorkflowStep->stage);
        $this->assertSame($cmcStaff->id, $revert->currentWorkflowStep->assigned_to);
        $this->assertSame(2, $revert->currentWorkflowStep->round);
        $this->assertSame('Penalty calculation needs another check.', $revert->latestRevertedWorkflowStep->remarks);
        $this->get(route('sdm-memos.index', $queue))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->isEmpty());
        $this->get(route('sdm-memos.index', [...$queue, 'verification_tab' => 'processed']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->sort()->values()->all() === [$forward->id, $secondForward->id]);

        $this->actingAs($forward->currentWorkflowStep->assignee)->post(route('sdm-memos.workflow.store', $forward), [
            'action' => 'return',
            'remarks' => 'Manager CMC requests another review.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($cmcSm)->get(route('sdm-memos.index', $queue))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->all() === [$forward->id]);
        $this->get(route('sdm-memos.index', [...$queue, 'verification_tab' => 'processed']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->all() === [$secondForward->id]);
    }

    public function test_all_memos_table_marks_a_reverted_sdm_and_displays_its_latest_revert_remarks(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead, 'CMC-SM-REVERT-LABEL');
        $cmcStaff = $this->user(UserRole::CmcStaff, 'CMC-STAFF-REVERT-LABEL');
        $memo = $this->memoAtStage('CMC-REVERT-LABEL', $cmcSm, WorkflowStepType::CmcVerification, $cmcStaff);

        $this->actingAs($cmcSm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return',
            'remarks' => 'Recheck the attendance evidence before reassessment.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($cmcSm)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee($memo->serial_number)
            ->assertSee('aria-label="Reverted SDM"', false)
            ->assertSee('Revert remarks:')
            ->assertSee('Recheck the attendance evidence before reassessment.');
    }

    public function test_workflow_upgrade_backfills_verification_for_an_existing_pending_cmc_assessment(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead, 'CMC-SM-04');
        $cmcStaff = $this->user(UserRole::CmcStaff, 'CMC-STAFF-04');
        $amCmc = $this->user(UserRole::ManagerOperations, 'AM-CMC-04');
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'LEGACY-CMC-ASSESSMENT',
            'station_id' => $this->station->id,
            'station_name' => $this->station->name,
            'line_code' => 'YL',
            'line_name' => 'Yellow Line',
            'status' => MemoStatus::CmcScrutiny->value,
            'workflow_version' => 2,
            'cmc_staff_id' => $cmcStaff->id,
            'submitted_at' => now(),
        ]);
        $this->step($memo, $cmcSm, WorkflowStepType::CmcAssignment)->update(['decision' => 'forwarded']);
        $assessment = $this->step($memo, $cmcStaff, WorkflowStepType::CmcAssessment);
        $this->step($memo, $amCmc, WorkflowStepType::AmCmcToDgm);
        $memo->update(['current_workflow_step_id' => $assessment->id]);

        $migration = require database_path('migrations/2026_10_09_000000_add_cmc_verification_workflow_steps.php');
        $migration->up();

        $memo->refresh();
        $verification = $memo->workflowSteps()->where('stage', WorkflowStepType::CmcVerification->value)->sole();
        $this->assertSame($assessment->id, $memo->current_workflow_step_id);
        $this->assertSame($cmcSm->id, $verification->assigned_to);
        $this->assertSame('pending', $verification->decision);
        $this->assertSame(3, $verification->sequence);
        $this->assertSame(4, $memo->workflowSteps()->where('stage', WorkflowStepType::AmCmcToDgm->value)->value('sequence'));
    }

    public function test_bulk_cmc_actions_reject_other_roles_and_sdms_assigned_to_another_cmc_sm(): void
    {
        $assignedCmcSm = $this->user(UserRole::CmcTeamLead, 'CMC-SM-05');
        $otherCmcSm = $this->user(UserRole::CmcTeamLead, 'CMC-SM-06');
        $operationsManager = $this->user(UserRole::ManagerOperations, 'AM-CMC-05');
        $cmcStaff = $this->user(UserRole::CmcStaff, 'CMC-STAFF-05');
        $memo = $this->memoAtStage('CMC-NOT-ASSIGNED', $assignedCmcSm, WorkflowStepType::CmcAssignment);

        $this->actingAs($operationsManager)->post(route('sdm-memos.bulk-cmc-action'), [
            'action' => 'assign',
            'memo_ids' => [$memo->id],
            'cmc_staff_id' => $cmcStaff->id,
        ])->assertForbidden();

        $this->actingAs($otherCmcSm)->post(route('sdm-memos.bulk-cmc-action'), [
            'action' => 'assign',
            'memo_ids' => [$memo->id],
            'cmc_staff_id' => $cmcStaff->id,
        ])->assertSessionHasErrors('memo_ids');

        $this->assertNull($memo->fresh()->cmc_staff_id);
    }

    private function user(UserRole $role, string $employeeNumber): User
    {
        $user = User::factory()->create(['employee_number' => $employeeNumber]);
        $user->assignRole($role->value);

        return $user;
    }

    private function memoAtStage(
        string $serialNumber,
        User $cmcSm,
        WorkflowStepType $currentStage,
        ?User $cmcStaff = null,
    ): ServiceDeficiencyMemo {
        $amCmc = $this->user(UserRole::ManagerOperations, 'AM-'.str($serialNumber)->afterLast('-'));
        $dgm = $this->user(UserRole::DeputyGeneralManager, 'DGM-'.str($serialNumber)->afterLast('-'));
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => $serialNumber,
            'station_id' => $this->station->id,
            'station_name' => $this->station->name,
            'line_code' => 'YL',
            'line_name' => 'Yellow Line',
            'incident_date' => '2026-10-01',
            'status' => $currentStage === WorkflowStepType::CmcAssignment
                ? MemoStatus::CmcAssignment->value
                : MemoStatus::CmcScrutiny->value,
            'workflow_version' => 2,
            'cmc_staff_id' => $cmcStaff?->id,
            'submitted_at' => now(),
        ]);

        $assignments = [
            WorkflowStepType::CmcAssignment->value => $cmcSm,
            WorkflowStepType::CmcAssessment->value => $cmcStaff,
            WorkflowStepType::CmcVerification->value => $cmcSm,
            WorkflowStepType::AmCmcToDgm->value => $amCmc,
            WorkflowStepType::DgmApproval->value => $dgm,
        ];
        $current = null;

        foreach ($assignments as $stageValue => $assignee) {
            $stage = WorkflowStepType::from($stageValue);
            $step = $this->step($memo, $assignee, $stage);
            if ($stage === $currentStage) {
                $current = $step;
            } elseif ($current === null) {
                $step->update(['decision' => 'forwarded', 'acted_at' => now()]);
            }
        }

        $memo->update(['current_workflow_step_id' => $current?->id]);

        return $memo->refresh();
    }

    private function step(ServiceDeficiencyMemo $memo, ?User $assignee, WorkflowStepType $stage): MemoWorkflowStep
    {
        return $memo->workflowSteps()->create([
            'round' => 1,
            'sequence' => $memo->workflowSteps()->count() + 1,
            'stage' => $stage->value,
            'role' => $stage->role()->value,
            'assigned_to' => $assignee?->id,
            'assigned_name' => $assignee?->name ?? 'Awaiting assignment',
            'assigned_employee_number' => $assignee?->employee_number,
            'decision' => 'pending',
        ]);
    }
}
