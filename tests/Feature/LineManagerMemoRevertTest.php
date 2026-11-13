<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LineManagerMemoRevertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_assigned_am_line_reverts_to_the_sm_who_forwarded_and_the_sm_can_resend(int $version): void
    {
        [$memo, $sm, $am] = $this->memoWithLineManager($version);

        $this->actingAs($am)->get(route('sdm-memos.show', $memo))
            ->assertOk()->assertSee('Revert to SM')->assertSee('Revert remark (mandatory)');

        $this->actingAs($am)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return',
            'remarks' => 'Please correct the incident details.',
        ])->assertRedirectToRoute('sdm-memos.index', ['category' => 'pending'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'SDM workflow updated.');

        $memo->refresh();
        $pending = $version === 2 ? $memo->currentWorkflowStep : $memo->stationManagerApproval;
        $this->assertSame($sm->id, $pending->assigned_to);
        $this->assertSame('pending', $pending->decision);
        $this->assertSame($version === 2 ? MemoStatus::AwaitingStationManager : MemoStatus::Submitted, $memo->status);
        $this->assertTrue($sm->can('update', $memo));
        $audit = $memo->auditLogs()->where('event', 'memo.workflow.return')->sole();
        $this->assertSame($am->id, $audit->actor_id);
        $this->assertSame($sm->id, $audit->after_values['returned_to']);
        $this->assertSame('Please correct the incident details.', $audit->after_values['remarks']);

        $this->actingAs($sm)->get(route('sdm-memos.index', ['category' => 'pending']))
            ->assertOk()->assertSee($memo->serial_number);
        $this->actingAs($sm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $am->employee_number,
        ])->assertSessionHasNoErrors();

        $memo->refresh();
        $pending = $version === 2 ? $memo->currentWorkflowStep : $memo->lineManagerApproval;
        $this->assertSame($am->id, $pending->assigned_to);
        $this->assertSame('pending', $pending->decision);
        if ($version === 2) {
            $this->assertSame(WorkflowStepType::LineReview, $pending->stage);
            $this->assertSame(1, $memo->workflowSteps()->where('decision', 'returned')->count());
            $this->assertSame(WorkflowStepType::AmCmcToScrutiny, $memo->workflowSteps()->where('sequence', '>', $pending->sequence)->first()->stage);
        }

        $this->actingAs($am)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            ...($version === 2 ? ['items' => [[
                'contract_penalty_clause_id' => $memo->serviceContract->penaltyClauses->first()->id,
                'proposed_amount' => 100,
            ]]] : []),
        ])->assertSessionHasNoErrors();
        $memo->refresh();
        $this->assertSame($version === 2 ? WorkflowStepType::AmCmcToScrutiny : WorkflowStage::OperationsManager,
            $version === 2 ? $memo->currentWorkflowStep->stage : $memo->current_stage);
        $this->actingAs($am)->get(route('sdm-memos.index'))
            ->assertOk()->assertSee('Please correct the incident details.');
        if ($version === 2) {
            $this->assertSame($sm->id, $memo->stationManagerWorkflowStep?->acted_by);
        }
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_revert_requires_a_nonblank_remark_without_changing_the_workflow(int $version): void
    {
        [$memo, , $am] = $this->memoWithLineManager($version);

        $this->actingAs($am)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return', 'remarks' => '   ',
        ])->assertSessionHasErrors(['remarks' => 'The remarks field is required.']);

        $this->assertSame($memo->status, $memo->fresh()->status);
        $this->assertSame(0, $memo->auditLogs()->where('event', 'memo.workflow.return')->count());
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_another_am_cannot_revert_the_memo(int $version): void
    {
        [$memo] = $this->memoWithLineManager($version);
        $otherAm = $this->user(UserRole::AssistantManager);

        $this->actingAs($otherAm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return', 'remarks' => 'Not assigned to me.',
        ])->assertForbidden();
        $this->assertSame($memo->status, $memo->fresh()->status);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_revert_does_not_route_to_a_different_sm_when_the_sender_is_disabled(int $version): void
    {
        [$memo, $sm, $am] = $this->memoWithLineManager($version);
        $sm->update(['is_enabled' => false]);

        $this->actingAs($am)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return', 'remarks' => 'Correct the details.',
        ])->assertSessionHasErrors(['action' => 'The Station Manager who sent this SDM must be enabled before it can be reverted.']);

        $this->assertSame($memo->status, $memo->fresh()->status);
        $this->assertSame(0, $memo->auditLogs()->where('event', 'memo.workflow.return')->count());
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_view_all_shows_the_latest_revert_remark_below_the_number_at_every_level(int $version): void
    {
        [$memo, $sm, $am] = $this->memoWithLineManager($version);
        $remark = 'Correct <script>alert(1)</script> incident details.';
        $this->actingAs($am)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return', 'remarks' => 'Earlier remark.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($sm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward', 'forward_to_employee_number' => $am->employee_number,
        ])->assertSessionHasNoErrors();
        $this->actingAs($am)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return', 'remarks' => $remark,
        ])->assertSessionHasNoErrors();

        foreach (UserRole::cases() as $role) {
            $viewer = $role === UserRole::Station ? $memo->creator : $this->user($role);
            $response = $this->actingAs($viewer)->get(route('sdm-memos.index'))->assertOk();

            if ($role === UserRole::Aft) {
                $response->assertDontSee($memo->serial_number)->assertDontSee(e($remark), false);

                continue;
            }

            $response
                ->assertSeeInOrder([$memo->serial_number, 'Revert remarks:', e($remark)], false)
                ->assertSee('aria-label="Reverted SDM"', false)
                ->assertDontSee($remark, false)
                ->assertDontSee('Earlier remark.');
        }
    }

    private function memoWithLineManager(int $version): array
    {
        $originalSm = $this->user(UserRole::StationManager);
        $sm = $this->user(UserRole::StationManager);
        $am = $this->user(UserRole::AssistantManager);
        $line = Line::query()->create(['code' => 'TL', 'name' => 'Test Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'TS', 'name' => 'Test Station', 'line_id' => $line->id, 'is_active' => true]);
        $creator = $this->user(UserRole::Station);
        $contractor = Contractor::query()->create(['name' => 'Test Contractor', 'is_active' => true]);
        $contract = ServiceContract::query()->create([
            'contract_group' => str()->ulid()->toString(), 'contractor_id' => $contractor->id,
            'service_type' => SdmType::Cfs->value, 'contract_number' => 'TEST-CONTRACT',
            'title' => 'Test Contract', 'effective_from' => now()->startOfYear(),
            'effective_to' => now()->endOfYear(),
        ]);
        $contract->seedPenaltyClauses();
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'LINE-REVERT-'.$version,
            'station_id' => $station->id,
            'service_contract_id' => $version === 2 ? $contract->id : null,
            'contractor_id' => $contractor->id,
            'created_by' => $creator->id,
            'station_name' => 'Test Station',
            'line_name' => 'Test Line',
            'line_code' => 'TL',
            'incident_date' => now()->toDateString(),
            'submitted_at' => now(),
            'workflow_version' => $version,
            'status' => $version === 2 ? MemoStatus::LineReview->value : MemoStatus::UnderReview->value,
            'current_stage' => WorkflowStage::LineManager->value,
        ]);

        if ($version === 2) {
            foreach (WorkflowStepType::generalRoute() as $index => $stage) {
                $assignee = match ($stage) {
                    WorkflowStepType::StationManager => $originalSm,
                    WorkflowStepType::LineReview => $am,
                    default => $this->user($stage->role()),
                };
                $step = $memo->workflowSteps()->create([
                    'round' => 1, 'sequence' => $index + 1,
                    'stage' => $stage->value, 'role' => $stage->role()->value,
                    'assigned_to' => $assignee->id, 'assigned_name' => $assignee->name,
                    'assigned_employee_number' => $assignee->employee_number,
                    'decision' => $index === 0 ? 'forwarded' : 'pending',
                    'acted_by' => $index === 0 ? $sm->id : null,
                    'acted_at' => $index === 0 ? now() : null,
                ]);
                if ($stage === WorkflowStepType::LineReview) {
                    $memo->update(['current_workflow_step_id' => $step->id]);
                }
            }
        } else {
            foreach (WorkflowStage::cases() as $index => $stage) {
                $assignee = match ($stage) {
                    WorkflowStage::StationManager => $originalSm,
                    WorkflowStage::LineManager => $am,
                    default => $this->user($stage->role()),
                };
                $memo->approvals()->create([
                    'sequence' => $index + 1, 'role' => $stage->value,
                    'assigned_to' => $assignee->id, 'assigned_name' => $assignee->name,
                    'assigned_employee_number' => $assignee->employee_number,
                    'decision' => $index === 0 ? 'approved' : 'pending',
                    'acted_by' => $index === 0 ? $sm->id : null,
                    'acted_at' => $index === 0 ? now() : null,
                ]);
            }
        }

        return [$memo->refresh(), $sm, $am];
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['employee_number' => fake()->unique()->numerify('EMP-########')]);
        $user->assignRole($role->value);

        return $user;
    }
}
