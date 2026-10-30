<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use App\Services\ServiceDeficiencyMemoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StationManagerForwardedEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('sdm.require_contract_workflow', false);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_forwarding_sm_can_edit_until_the_assigned_am_first_opens_the_sdm(int $version): void
    {
        $this->travelTo(now()->startOfSecond());
        [$memo, $sm, $am] = $this->memoAwaitingStationManager($version);
        $this->forward($memo, $sm, $am);
        $status = $memo->refresh()->status;
        $currentStepId = $memo->current_workflow_step_id;
        $submittedAt = $memo->submitted_at->toIso8601String();
        $this->travel(3)->days();

        $this->actingAs($sm)->get(route('sdm-memos.show', $memo))
            ->assertOk()->assertSee(route('sdm-memos.edit', $memo), false);
        $this->actingAs($sm)->get(route('sdm-memos.edit', $memo))->assertOk();
        $this->actingAs($sm)->post(route('sdm-memos.update', $memo), $this->editData())
            ->assertSessionHasNoErrors()->assertRedirect(route('sdm-memos.show', $memo));

        $this->assertSame('Corrected after forwarding', $memo->refresh()->staff_name);
        $this->assertSame($status, $memo->status);
        $this->assertSame($currentStepId, $memo->current_workflow_step_id);
        $this->assertSame($submittedAt, $memo->submitted_at->toIso8601String());
        $editAudit = AuditLog::query()->where('event', 'memo.station_manager.edited')->sole();
        $this->assertSame($sm->id, $editAudit->actor_id);
        $this->assertSame('Original staff', $editAudit->before_values['staff_name']);
        $this->assertSame('Corrected after forwarding', $editAudit->after_values['staff_name']);
        $staleMemo = $memo->fresh();

        $this->actingAs($am)->get(route('sdm-memos.show', $memo))->assertOk();

        $openedAt = $memo->refresh()->line_manager_opened_at;
        $this->assertNotNull($openedAt);
        $this->assertTrue($openedAt->equalTo(now()));
        $audit = AuditLog::query()->where('event', 'memo.line_manager.opened')->sole();
        $this->assertSame($am->id, $audit->actor_id);
        $this->assertSame($memo->id, $audit->auditable_id);
        $this->assertNull($audit->before_values['line_manager_opened_at']);
        $this->assertSame($openedAt->toIso8601String(), $audit->after_values['line_manager_opened_at']);
        $this->travel(1)->hours();
        $this->actingAs($am)->get(route('sdm-memos.show', $memo))->assertOk();
        $this->assertTrue($memo->refresh()->line_manager_opened_at->equalTo($openedAt));
        $this->assertSame(1, AuditLog::query()->where('event', 'memo.line_manager.opened')->count());

        $this->actingAs($sm)->get(route('sdm-memos.show', $memo))
            ->assertOk()->assertDontSee(route('sdm-memos.edit', $memo), false);
        $this->actingAs($sm)->get(route('sdm-memos.edit', $memo))->assertForbidden();
        $this->actingAs($sm)->post(route('sdm-memos.update', $memo), $this->editData('Blocked stale form'))->assertForbidden();

        try {
            app(ServiceDeficiencyMemoService::class)->updateDraft($staleMemo, $sm, $this->editData('Blocked stale model'));
            $this->fail('An AM first-open lock must also reject a stale model.');
        } catch (AuthorizationException) {
            $this->assertSame('Corrected after forwarding', $memo->refresh()->staff_name);
        }
        $this->assertSame(1, AuditLog::query()->where('event', 'memo.station_manager.edited')->count());
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_only_the_assigned_am_opening_the_forwarded_sdm_locks_sm_editing(int $version): void
    {
        [$memo, $sm, $am, $creator] = $this->memoAwaitingStationManager($version);
        $otherAm = $this->user(UserRole::AssistantManager);
        $otherSm = $this->user(UserRole::StationManager);

        $this->actingAs($am)->get(route('sdm-memos.show', $memo))->assertOk();
        $this->forward($memo, $sm, $am);
        $this->actingAs($otherAm)->get(route('sdm-memos.show', $memo))->assertOk();
        $this->actingAs($creator)->get(route('sdm-memos.show', $memo))->assertOk();
        $this->actingAs($am)->get(route('sdm-memos.index'))->assertOk();
        $this->actingAs($otherSm)->get(route('sdm-memos.edit', $memo))->assertForbidden();
        $this->actingAs($otherSm)->post(route('sdm-memos.update', $memo), $this->editData())->assertForbidden();

        $this->assertNull($memo->refresh()->line_manager_opened_at);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'memo.line_manager.opened']);
        $this->actingAs($sm)->get(route('sdm-memos.edit', $memo))->assertOk();
        $this->actingAs($am)->get(route('sdm-memos.show', $memo))->assertOk();
        $this->actingAs($sm)->get(route('sdm-memos.edit', $memo))->assertForbidden();
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_am_handover_does_not_restore_sm_edit_access_after_the_first_open(int $version): void
    {
        [$memo, $sm, $am] = $this->memoAwaitingStationManager($version);
        $replacement = $this->user(UserRole::AssistantManager);
        $this->forward($memo, $sm, $am);
        $this->actingAs($am)->get(route('sdm-memos.show', $memo))->assertOk();
        $openedAt = $memo->refresh()->line_manager_opened_at;

        $this->actingAs($am)->patch(route('sdm-memos.line-manager.update', $memo), [
            'line_manager_employee_number' => $replacement->employee_number,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNotNull($openedAt);
        $this->assertTrue($memo->refresh()->line_manager_opened_at->equalTo($openedAt));
        $this->actingAs($sm)->get(route('sdm-memos.edit', $memo))->assertForbidden();
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_sm_cannot_edit_after_am_cancels_without_opening_the_detail_page(int $version): void
    {
        [$memo, $sm, $am] = $this->memoAwaitingStationManager($version);
        $this->forward($memo, $sm, $am);

        $this->actingAs($am)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'cancel',
            'remarks' => 'Cancelled by assigned AM.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(MemoStatus::Cancelled, $memo->refresh()->status);
        $this->actingAs($sm)->get(route('sdm-memos.edit', $memo))->assertForbidden();
        $this->actingAs($sm)->post(route('sdm-memos.update', $memo), $this->editData())->assertForbidden();
    }

    /** @return array{ServiceDeficiencyMemo, User, User, User} */
    private function memoAwaitingStationManager(int $version): array
    {
        $line = Line::query()->create(['code' => 'AM-OPEN', 'name' => 'AM Open Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'AM-OPEN-01', 'name' => 'AM Open Station', 'line_id' => $line->id, 'is_active' => true]);
        $creator = $this->user(UserRole::Station);
        $creator->update(['station_id' => $station->id]);
        $sm = $this->user(UserRole::StationManager);
        $am = $this->user(UserRole::AssistantManager);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'AM-FIRST-OPEN',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_name' => $line->name,
            'line_code' => $line->code,
            'staff_name' => 'Original staff',
            'status' => $version === 2 ? MemoStatus::AwaitingStationManager->value : MemoStatus::Submitted->value,
            'workflow_version' => $version,
            'current_stage' => WorkflowStage::StationManager->value,
            'created_by' => $creator->id,
            'submitted_at' => now(),
        ]);
        if ($version === 2) {
            $contractor = Contractor::query()->create(['name' => 'AM Open Contractor', 'is_active' => true]);
            $contract = ServiceContract::query()->create([
                'contract_group' => 'AM-FIRST-OPEN-CONTRACT',
                'contractor_id' => $contractor->id,
                'service_type' => SdmType::Cfs->value,
                'contract_number' => 'AM-OPEN-CONTRACT',
                'title' => 'AM Open Contract',
                'effective_from' => now()->subYear()->toDateString(),
                'effective_to' => now()->addYear()->toDateString(),
                'cmc_staff_id' => $this->user(UserRole::CmcStaff)->id,
                'status' => 'published',
            ]);
            $memo->update(['service_contract_id' => $contract->id, 'contractor_id' => $contractor->id]);
        }
        foreach ([WorkflowStage::StationManager, WorkflowStage::LineManager] as $index => $stage) {
            $assignee = $index === 0 ? $sm : $am;
            $attributes = [
                'role' => $version === 2 ? $stage->role()->value : $stage->value,
                'sequence' => $index + 1,
                'assigned_to' => $assignee->id,
                'assigned_name' => $assignee->name,
                'assigned_employee_number' => $assignee->employee_number,
                'decision' => 'pending',
            ];
            if ($version === 2) {
                $step = $memo->workflowSteps()->create([
                    ...$attributes,
                    'round' => 1,
                    'stage' => $index === 0 ? WorkflowStepType::StationManager->value : WorkflowStepType::LineReview->value,
                ]);
                if ($index === 0) {
                    $memo->update(['current_workflow_step_id' => $step->id]);
                }
            } else {
                $memo->approvals()->create($attributes);
            }
        }

        return [$memo, $sm, $am, $creator];
    }

    private function forward(ServiceDeficiencyMemo $memo, User $sm, User $am): void
    {
        $this->actingAs($sm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $am->employee_number,
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['employee_number' => 'EMP-'.(User::query()->count() + 1)]);
        $user->assignRole($role->value);

        return $user;
    }

    private function editData(string $staffName = 'Corrected after forwarding'): array
    {
        return [
            'service_type' => SdmType::Cfs->value,
            'staff_name' => $staffName,
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Updated before AM opened.'],
        ];
    }
}
