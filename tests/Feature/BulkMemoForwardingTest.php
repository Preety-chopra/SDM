<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\Line;
use App\Models\MemoWorkflowStep;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkMemoForwardingTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $line = Line::query()->create(['code' => 'RED', 'name' => 'Red Line', 'is_active' => true]);
        $this->station = Station::query()->create([
            'code' => 'RED-01',
            'name' => 'Central Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
    }

    public function test_am_cmc_can_bulk_forward_selected_memos_to_the_requested_destination(): void
    {
        $amCmc = $this->user(UserRole::ManagerOperations);
        $cmcTeamSm = $this->user(UserRole::CmcTeamLead);
        $dgmCmc = $this->user(UserRole::DeputyGeneralManager);
        $toScrutiny = $this->memoAtAmStage('BULK-SCRUTINY', $amCmc, WorkflowStepType::AmCmcToScrutiny, $cmcTeamSm);
        $toDgm = $this->memoAtAmStage('BULK-DGM', $amCmc, WorkflowStepType::AmCmcToDgm, $dgmCmc);

        $this->actingAs($amCmc)
            ->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('Bulk forward AM/CMC memos')
            ->assertSee('name="memo_ids[]"', false)
            ->assertSee('Select every matching memo across all pages');

        $this->post(route('sdm-memos.bulk-forward'), [
            'destination' => WorkflowStepType::AmCmcToScrutiny->value,
            'memo_ids' => [$toScrutiny->id],
        ])->assertRedirect(route('sdm-memos.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(MemoStatus::CmcAssignment, $toScrutiny->fresh()->status);
        $this->assertSame($cmcTeamSm->id, $toScrutiny->fresh()->currentWorkflowStep->assigned_to);

        $this->post(route('sdm-memos.bulk-forward'), [
            'destination' => WorkflowStepType::AmCmcToDgm->value,
            'memo_ids' => [$toDgm->id],
        ])->assertRedirect(route('sdm-memos.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(MemoStatus::DgmApproval, $toDgm->fresh()->status);
        $this->assertSame($dgmCmc->id, $toDgm->fresh()->currentWorkflowStep->assigned_to);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_select_all_forwards_every_eligible_memo_across_pages_and_honours_filters(): void
    {
        $amCmc = $this->user(UserRole::ManagerOperations);
        $cmcTeamSm = $this->user(UserRole::CmcTeamLead);

        $matching = collect(range(1, 17))->map(fn (int $number): ServiceDeficiencyMemo => $this->memoAtAmStage(
            'FILTERED-'.$number,
            $amCmc,
            WorkflowStepType::AmCmcToScrutiny,
            $cmcTeamSm,
            SdmType::Cfs,
        ));
        $notMatching = $this->memoAtAmStage(
            'NOT-FILTERED',
            $amCmc,
            WorkflowStepType::AmCmcToScrutiny,
            $cmcTeamSm,
            SdmType::Ticketing,
        );
        $outsideDateRange = $this->memoAtAmStage(
            'OUTSIDE-DATE-RANGE',
            $amCmc,
            WorkflowStepType::AmCmcToScrutiny,
            $cmcTeamSm,
            SdmType::Cfs,
            '2026-09-25',
        );

        $this->actingAs($amCmc)->post(route('sdm-memos.bulk-forward'), [
            'destination' => WorkflowStepType::AmCmcToScrutiny->value,
            'select_all' => '1',
            'service_type' => SdmType::Cfs->value,
            'incident_date_from' => '2026-09-10',
            'incident_date_to' => '2026-09-20',
        ])->assertRedirect(route('sdm-memos.index', [
            'service_type' => SdmType::Cfs->value,
            'incident_date_from' => '2026-09-10',
            'incident_date_to' => '2026-09-20',
        ]))
            ->assertSessionHas('status', '17 SDMs forwarded to CMC Team SM for scrutiny.');

        $this->assertSame(
            17,
            ServiceDeficiencyMemo::query()->whereKey($matching->pluck('id'))->where('status', MemoStatus::CmcAssignment->value)->count(),
        );
        $this->assertSame(MemoStatus::AmCmcReview, $notMatching->fresh()->status);
        $this->assertSame(MemoStatus::AmCmcReview, $outsideDateRange->fresh()->status);
    }

    public function test_select_all_without_filters_forwards_every_memo_at_the_selected_stage(): void
    {
        $amCmc = $this->user(UserRole::ManagerOperations);
        $cmcTeamSm = $this->user(UserRole::CmcTeamLead);
        $dgmCmc = $this->user(UserRole::DeputyGeneralManager);
        $first = $this->memoAtAmStage('ALL-FIRST', $amCmc, WorkflowStepType::AmCmcToScrutiny, $cmcTeamSm);
        $second = $this->memoAtAmStage('ALL-SECOND', $amCmc, WorkflowStepType::AmCmcToScrutiny, $cmcTeamSm);
        $otherStage = $this->memoAtAmStage('ALL-OTHER-STAGE', $amCmc, WorkflowStepType::AmCmcToDgm, $dgmCmc);

        $this->actingAs($amCmc)->post(route('sdm-memos.bulk-forward'), [
            'destination' => WorkflowStepType::AmCmcToScrutiny->value,
            'select_all' => '1',
        ])->assertRedirect(route('sdm-memos.index'))
            ->assertSessionHas('status', '2 SDMs forwarded to CMC Team SM for scrutiny.');

        $this->assertSame(MemoStatus::CmcAssignment, $first->fresh()->status);
        $this->assertSame(MemoStatus::CmcAssignment, $second->fresh()->status);
        $this->assertSame(MemoStatus::AmCmcReview, $otherStage->fresh()->status);
    }

    public function test_bulk_forwarding_rejects_unauthorised_users_and_memos_at_the_wrong_am_stage(): void
    {
        $amCmc = $this->user(UserRole::ManagerOperations);
        $otherAmCmc = $this->user(UserRole::ManagerOperations);
        $cmcTeamSm = $this->user(UserRole::CmcTeamLead);
        $memo = $this->memoAtAmStage('NOT-MINE', $otherAmCmc, WorkflowStepType::AmCmcToScrutiny, $cmcTeamSm);

        $this->actingAs($cmcTeamSm)->post(route('sdm-memos.bulk-forward'), [
            'destination' => WorkflowStepType::AmCmcToScrutiny->value,
            'memo_ids' => [$memo->id],
        ])->assertForbidden();

        $this->actingAs($amCmc)->post(route('sdm-memos.bulk-forward'), [
            'destination' => WorkflowStepType::AmCmcToDgm->value,
            'memo_ids' => [$memo->id],
        ])->assertSessionHasErrors('memo_ids');

        $this->assertSame(MemoStatus::AmCmcReview, $memo->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function memoAtAmStage(
        string $serialNumber,
        User $amCmc,
        WorkflowStepType $amStage,
        User $nextAssignee,
        SdmType $serviceType = SdmType::Cfs,
        string $incidentDate = '2026-09-15',
    ): ServiceDeficiencyMemo {
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => $serviceType->value,
            'serial_number' => $serialNumber,
            'station_id' => $this->station->id,
            'station_name' => $this->station->name,
            'line_code' => 'RED',
            'line_name' => 'Red Line',
            'incident_date' => $incidentDate,
            'status' => MemoStatus::AmCmcReview->value,
            'workflow_version' => 2,
            'submitted_at' => now(),
        ]);

        $sequence = $amStage === WorkflowStepType::AmCmcToScrutiny ? 3 : 6;
        $current = $this->step($memo, $amCmc, $amStage, $sequence);
        $nextStage = $amStage === WorkflowStepType::AmCmcToScrutiny
            ? WorkflowStepType::CmcAssignment
            : WorkflowStepType::DgmApproval;
        $this->step($memo, $nextAssignee, $nextStage, $sequence + 1);
        $memo->update(['current_workflow_step_id' => $current->id]);

        return $memo->refresh();
    }

    private function step(ServiceDeficiencyMemo $memo, User $assignee, WorkflowStepType $stage, int $sequence): MemoWorkflowStep
    {
        return MemoWorkflowStep::query()->create([
            'memo_id' => $memo->id,
            'round' => 1,
            'sequence' => $sequence,
            'stage' => $stage->value,
            'role' => $stage->role()->value,
            'assigned_to' => $assignee->id,
            'assigned_name' => $assignee->name,
            'assigned_employee_number' => $assignee->employee_number,
            'decision' => 'pending',
        ]);
    }
}
