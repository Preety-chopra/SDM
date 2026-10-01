<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ReportingRelationship;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SdmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('sdm.require_contract_workflow', false);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_submission_is_blocked_until_the_reporting_hierarchy_is_complete(): void
    {
        [$stationUser] = $this->workflowUsers(false);

        $this->actingAs($stationUser)
            ->post(route('sdm-memos.store-and-submit'), $this->validMemoData())
            ->assertSessionHasErrors('hierarchy');

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame(MemoStatus::Draft, $memo->status);
        $this->assertNull($memo->submitted_at);
    }

    public function test_administrator_maps_each_approval_role_to_its_immediate_supervisor(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm] = $this->workflowUsers(false);
        $administrator = $this->user(UserRole::Administrator);

        foreach ([
            [$stationManager, $lineManager],
            [$lineManager, $operationsManager],
            [$operationsManager, $dgm],
        ] as [$subordinate, $supervisor]) {
            $this->actingAs($administrator)->put(route('hierarchies.update'), [
                'subordinate_id' => $subordinate->id,
                'supervisor_id' => $supervisor->id,
            ])->assertRedirect();
        }

        $this->assertDatabaseHas('reporting_relationships', [
            'subordinate_id' => $stationManager->id,
            'supervisor_id' => $lineManager->id,
        ]);
        $this->assertDatabaseHas('reporting_relationships', [
            'subordinate_id' => $lineManager->id,
            'supervisor_id' => $operationsManager->id,
        ]);
        $this->assertDatabaseHas('reporting_relationships', [
            'subordinate_id' => $operationsManager->id,
            'supervisor_id' => $dgm->id,
        ]);

        $this->actingAs($stationUser)
            ->post(route('sdm-memos.store-and-submit'), $this->validMemoData())
            ->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame(
            [$stationManager->id, $lineManager->id, $operationsManager->id, $dgm->id],
            $memo->approvals()->orderBy('sequence')->pluck('assigned_to')->all(),
        );
    }

    public function test_hierarchy_page_presents_adjacent_role_mappings_instead_of_repeating_every_role_per_station(): void
    {
        $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)->get(route('hierarchies.index'))
            ->assertOk()
            ->assertSee('Station Managers → Assistant Managers')
            ->assertSee('Assistant Managers → Managers (Operations)')
            ->assertSee('Managers (Operations) → DGM/O')
            ->assertDontSee('Stations → Station Managers')
            ->assertDontSee('name="station_id"', false);
    }

    public function test_sdm_moves_through_every_approver_before_final_dgm_approval(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm] = $this->workflowUsers();

        $this->actingAs($stationUser)
            ->post(route('sdm-memos.store-and-submit'), $this->validMemoData())
            ->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertSame(WorkflowStage::StationManager, $memo->current_stage);
        $this->assertCount(4, $memo->approvals);
        $this->assertSame([
            WorkflowStage::StationManager,
            WorkflowStage::LineManager,
            WorkflowStage::OperationsManager,
            WorkflowStage::DeputyGeneralManager,
        ], $memo->approvals()->orderBy('sequence')->get()->pluck('role')->all());
        $this->actingAs($stationUser)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee($stationManager->employee_number)
            ->assertDontSee($lineManager->employee_number)
            ->assertDontSee($operationsManager->employee_number)
            ->assertDontSee($dgm->employee_number);
        $this->actingAs($stationManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee($lineManager->employee_number)
            ->assertDontSee($operationsManager->employee_number)
            ->assertDontSee($dgm->employee_number)
            ->assertSee('Remarks / cancellation request to AM (optional)')
            ->assertSee('name="action" value="request_cancellation"', false)
            ->assertSee('Request for cancellation')
            ->assertSee('&quot;label&quot;:&quot;Line&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;Station&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;SDM type&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;Selected Deficiency&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;Incident date and time&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;Submitted on&quot;', false)
            ->assertSee('data-confirmation-show-action="false"', false)
            ->assertSee('data-confirmation-fields="[]"', false)
            ->assertDontSee('value="cancel"', false)
            ->assertDontSee('Cancel SDM');
        $this->actingAs($stationManager)->get(route('sdm-memos.index'))->assertOk()->assertSee($memo->serial_number);
        $this->actingAs($lineManager)->get(route('sdm-memos.index'))->assertOk()->assertSee($memo->serial_number);
        $this->actingAs($operationsManager)->get(route('sdm-memos.index'))->assertOk()->assertSee($memo->serial_number);

        foreach ([
            [$stationManager, 'forward', WorkflowStage::LineManager, MemoStatus::UnderReview, $lineManager->employee_number],
            [$lineManager, 'forward', WorkflowStage::OperationsManager, MemoStatus::UnderReview, null],
            [$operationsManager, 'forward', WorkflowStage::DeputyGeneralManager, MemoStatus::UnderReview, null],
            [$dgm, 'approve', WorkflowStage::DeputyGeneralManager, MemoStatus::Closed, null],
        ] as [$actor, $action, $stage, $status, $forwardTo]) {
            $this->actingAs($actor)
                ->post(route('sdm-memos.workflow.store', $memo), array_filter([
                    'action' => $action,
                    'forward_to_employee_number' => $forwardTo,
                ]))
                ->assertRedirect(route('sdm-memos.show', $memo));

            $memo->refresh();
            $this->assertSame($stage, $memo->current_stage);
            $this->assertSame($status, $memo->status);
        }
    }

    public function test_memo_table_displays_when_the_current_status_was_last_updated(): void
    {
        Carbon::setTestNow('2026-09-01 10:15:00');
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();

        $this->actingAs($stationUser)
            ->post(route('sdm-memos.store-and-submit'), $this->validMemoData('CFS-STATUS-TIMESTAMP'))
            ->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-STATUS-TIMESTAMP')->sole();
        $this->actingAs($stationManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('Last Updated on')
            ->assertSee('01-09-2026 10:15');

        Carbon::setTestNow('2026-09-01 11:20:00');
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();

        $this->actingAs($lineManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('01-09-2026 11:20');
    }

    public function test_submission_requires_the_station_managers_employee_number(): void
    {
        [$stationUser, $stationManager] = $this->workflowUsers();
        $inactiveStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-INACTIVE',
            'is_enabled' => false,
        ]);
        $lineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-NOT-SM']);
        $data = $this->validMemoData();
        unset($data['station_manager_employee_number']);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $data)
            ->assertSessionHasErrors('station_manager_employee_number');

        $data['station_manager_employee_number'] = 'UNKNOWN-SM';
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $data)
            ->assertSessionHasErrors('station_manager_employee_number');

        $data['station_manager_employee_number'] = $inactiveStationManager->employee_number;
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $data)
            ->assertSessionHasErrors('station_manager_employee_number');

        $data['station_manager_employee_number'] = $lineManager->employee_number;
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $data)
            ->assertSessionHasErrors('station_manager_employee_number');

        $data['station_manager_employee_number'] = $stationManager->employee_number;
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $data)
            ->assertRedirect();

        $this->assertSame($stationManager->id, ServiceDeficiencyMemo::query()->sole()->approvals()->where('role', WorkflowStage::StationManager->value)->value('assigned_to'));
    }

    public function test_station_controller_can_edit_a_submitted_memo_until_two_days_or_station_manager_forwarding(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData(),
            'station_manager_employee_number' => $stationManager->employee_number,
        ]);
        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame('2026-09-01 10:00:00', $memo->submitted_at?->format('Y-m-d H:i:s'));

        $this->actingAs($stationUser)->get(route('sdm-memos.edit', $memo))->assertOk();
        Carbon::setTestNow('2026-09-01 11:00:00');
        $this->actingAs($stationUser)->post(route('sdm-memos.update', $memo), [
            ...$this->validMemoData($memo->serial_number),
            'staff_name' => 'Corrected Staff Name',
        ])->assertRedirect();
        $memo->refresh();
        $this->assertSame('Corrected Staff Name', $memo->staff_name);
        $this->assertSame('2026-09-01 10:00:00', $memo->submitted_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-09-03 11:00:01');
        $this->actingAs($stationUser)->get(route('sdm-memos.edit', $memo))->assertForbidden();

        Carbon::setTestNow('2026-09-01 11:00:00');
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();
        $this->actingAs($stationUser)->get(route('sdm-memos.edit', $memo))->assertForbidden();
    }

    public function test_station_controller_can_change_the_forwarded_station_manager_within_two_days_and_the_change_is_audited(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        [$stationUser, $originalStationManager] = $this->workflowUsers();
        $replacementStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-REPLACEMENT',
        ]);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData(),
            'station_manager_employee_number' => $originalStationManager->employee_number,
        ]);
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationUser)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Change forwarded SM')
            ->assertSee('name="station_manager_employee_number"', false)
            ->assertSee($replacementStationManager->employee_number.' - '.$replacementStationManager->name);

        Carbon::setTestNow('2026-09-03 09:59:59');
        $this->actingAs($stationUser)->patch(route('sdm-memos.station-manager.update', $memo), [
            'station_manager_employee_number' => $replacementStationManager->employee_number,
        ])->assertRedirect(route('sdm-memos.show', $memo));

        $assignment = $memo->approvals()->where('role', WorkflowStage::StationManager->value)->sole();
        $this->assertSame($replacementStationManager->id, $assignment->assigned_to);
        $this->assertSame($replacementStationManager->name, $assignment->assigned_name);
        $this->assertSame($replacementStationManager->employee_number, $assignment->assigned_employee_number);
        $this->assertSame('2026-09-01 10:00:00', $memo->fresh()->submitted_at?->format('Y-m-d H:i:s'));

        $audit = AuditLog::query()->where('event', 'memo.station_manager.reassigned')->sole();
        $this->assertSame($stationUser->id, $audit->actor_id);
        $this->assertSame([
            'station_manager_id' => $originalStationManager->id,
            'station_manager_name' => $originalStationManager->name,
            'station_manager_employee_number' => $originalStationManager->employee_number,
        ], $audit->before_values);
        $this->assertSame([
            'station_manager_id' => $replacementStationManager->id,
            'station_manager_name' => $replacementStationManager->name,
            'station_manager_employee_number' => $replacementStationManager->employee_number,
        ], $audit->after_values);
    }

    public function test_station_controller_cannot_change_the_forwarded_station_manager_after_two_days_or_after_sm_action(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $replacementStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-REPLACEMENT',
        ]);
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData(),
            'station_manager_employee_number' => $stationManager->employee_number,
        ]);
        $memo = ServiceDeficiencyMemo::query()->sole();

        Carbon::setTestNow('2026-09-03 10:00:01');
        $this->actingAs($stationUser)->patch(route('sdm-memos.station-manager.update', $memo), [
            'station_manager_employee_number' => $replacementStationManager->employee_number,
        ])->assertForbidden();

        Carbon::setTestNow('2026-09-01 11:00:00');
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();

        $this->actingAs($stationUser)->patch(route('sdm-memos.station-manager.update', $memo), [
            'station_manager_employee_number' => $replacementStationManager->employee_number,
        ])->assertForbidden();

        $this->assertSame($stationManager->id, $memo->approvals()->where('role', WorkflowStage::StationManager->value)->value('assigned_to'));
        $this->assertDatabaseMissing('audit_logs', ['event' => 'memo.station_manager.reassigned']);
    }

    public function test_station_controller_can_change_the_forwarded_station_manager_for_contract_workflow_memos(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        [$stationUser, $originalStationManager, , , , $station] = $this->workflowUsers();
        $replacementStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-CONTRACT-REPLACEMENT',
        ]);

        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-CONTRACT-REASSIGN',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_name' => $station->metroLine->name,
            'line_code' => $station->metroLine->code,
            'status' => MemoStatus::AwaitingStationManager->value,
            'workflow_version' => 2,
            'created_by' => $stationUser->id,
            'updated_by' => $stationUser->id,
            'submitted_at' => now(),
        ]);
        $stationManagerStep = $memo->workflowSteps()->create([
            'round' => 1,
            'sequence' => 1,
            'stage' => WorkflowStepType::StationManager->value,
            'role' => UserRole::StationManager->value,
            'assigned_to' => $originalStationManager->id,
            'assigned_name' => $originalStationManager->name,
            'assigned_employee_number' => $originalStationManager->employee_number,
            'decision' => 'pending',
        ]);
        $memo->update(['current_workflow_step_id' => $stationManagerStep->id]);

        $this->actingAs($stationUser)->patch(route('sdm-memos.station-manager.update', $memo), [
            'station_manager_employee_number' => $replacementStationManager->employee_number,
        ])->assertRedirect(route('sdm-memos.show', $memo));

        $stationManagerStep->refresh();
        $this->assertSame($replacementStationManager->id, $stationManagerStep->assigned_to);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $stationUser->id,
            'event' => 'memo.station_manager.reassigned',
            'auditable_id' => $memo->id,
        ]);
    }

    public function test_station_manager_can_handover_their_pending_sdm_to_another_active_station_manager(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        [$stationUser, $stationManager] = $this->workflowUsers();
        $replacementStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-HANDOVER',
            'name' => 'Handover Manager',
        ]);
        $inactiveStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-INACTIVE-HANDOVER',
            'is_enabled' => false,
        ]);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData(),
            'station_manager_employee_number' => $stationManager->employee_number,
        ]);
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Handover pending SDM')
            ->assertSee('name="station_manager_employee_number"', false)
            ->assertSee($replacementStationManager->employee_number.' - '.$replacementStationManager->name)
            ->assertDontSee($stationManager->employee_number.' - '.$stationManager->name)
            ->assertDontSee($inactiveStationManager->employee_number.' - '.$inactiveStationManager->name);

        Carbon::setTestNow('2026-09-10 10:00:00');
        $this->actingAs($stationManager)->patch(route('sdm-memos.station-manager.update', $memo), [
            'station_manager_employee_number' => $replacementStationManager->employee_number,
        ])->assertRedirect(route('sdm-memos.show', $memo));

        $assignment = $memo->approvals()->where('role', WorkflowStage::StationManager->value)->sole();
        $this->assertSame($replacementStationManager->id, $assignment->assigned_to);
        $this->assertSame($replacementStationManager->name, $assignment->assigned_name);
        $this->assertSame($replacementStationManager->employee_number, $assignment->assigned_employee_number);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $stationManager->id,
            'event' => 'memo.station_manager.handed_over',
            'auditable_id' => $memo->id,
        ]);
    }

    public function test_memo_tabs_separate_pending_assignments_from_sdms_transferred_to_the_station_manager(): void
    {
        [$stationUser, $sourceStationManager, $lineManager] = $this->workflowUsers();
        $recipientStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-INBOX-RECIPIENT',
        ]);
        ReportingRelationship::query()->create([
            'subordinate_id' => $recipientStationManager->id,
            'supervisor_id' => $lineManager->id,
        ]);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData('CFS-TRANSFERRED-INBOX'),
            'station_manager_employee_number' => $sourceStationManager->employee_number,
        ]);
        $transferredMemo = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-TRANSFERRED-INBOX')->sole();
        $this->actingAs($sourceStationManager)->patch(route('sdm-memos.station-manager.update', $transferredMemo), [
            'station_manager_employee_number' => $recipientStationManager->employee_number,
        ])->assertRedirect();

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData('CFS-PENDING-INBOX'),
            'station_manager_employee_number' => $recipientStationManager->employee_number,
        ]);
        $pendingMemo = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-PENDING-INBOX')->sole();

        $this->actingAs($recipientStationManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('All Memos')
            ->assertSee('Pending Memos')
            ->assertSee('Transferred Memos')
            ->assertSee(route('sdm-memos.index', ['category' => 'pending']), false)
            ->assertSee(route('sdm-memos.index', ['category' => 'transferred']), false);

        $this->actingAs($recipientStationManager)->get(route('sdm-memos.index', ['category' => 'pending']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->pluck('id')->all() === [$pendingMemo->id])
            ->assertDontSee('CFS-PENDING-INBOX')
            ->assertDontSee('CFS-TRANSFERRED-INBOX');

        $this->actingAs($recipientStationManager)->get(route('sdm-memos.index', ['category' => 'transferred']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->pluck('id')->all() === [$transferredMemo->id])
            ->assertDontSee('CFS-TRANSFERRED-INBOX')
            ->assertDontSee('CFS-PENDING-INBOX');
    }

    public function test_pending_memos_tab_shows_the_current_non_sm_workflow_assignee(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData('CFS-AM-PENDING-INBOX'));
        $memo = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-AM-PENDING-INBOX')->sole();
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();

        $this->actingAs($lineManager)->get(route('sdm-memos.index', ['category' => 'pending']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->pluck('id')->all() === [$memo->id])
            ->assertDontSee('CFS-AM-PENDING-INBOX');

        $this->actingAs($stationManager)->get(route('sdm-memos.index', ['category' => 'pending']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->isEmpty())
            ->assertDontSee('CFS-AM-PENDING-INBOX');
    }

    public function test_only_line_manager_sees_the_centered_cancellation_dialog_on_memo_lists(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $this->actingAs($stationUser)
            ->post(route('sdm-memos.store-and-submit'), $this->validMemoData('CFS-AM-DLG'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $memo = ServiceDeficiencyMemo::query()->sole();

        foreach ([[], ['category' => 'pending']] as $parameters) {
            $this->actingAs($stationManager)->get(route('sdm-memos.index', $parameters))
                ->assertOk()
                ->assertDontSee('id="cancel-memo-'.$memo->id.'"', false)
                ->assertDontSee('Cancellation remarks')
                ->assertDontSee('name="action" value="cancel"', false);
        }

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();

        foreach ([[], ['category' => 'pending']] as $parameters) {
            $this->actingAs($lineManager)->get(route('sdm-memos.index', $parameters))
                ->assertOk()
                ->assertSee('@click="cancellationOpen = true"', false)
                ->assertSee('x-teleport="body"', false)
                ->assertSee('id="cancel-memo-'.$memo->id.'"', false)
                ->assertSee('fixed inset-0 z-50 grid place-items-center', false)
                ->assertSee(route('sdm-memos.workflow.store', $memo), false)
                ->assertSee('Cancellation remarks')
                ->assertDontSee('<details class="relative">', false);
        }
    }

    public function test_line_manager_can_handover_their_pending_sdm_to_another_active_line_manager(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $replacementLineManager = $this->user(UserRole::AssistantManager, [
            'employee_number' => 'AM-HANDOVER',
            'name' => 'Replacement Line Manager',
        ]);
        $inactiveLineManager = $this->user(UserRole::AssistantManager, [
            'employee_number' => 'AM-INACTIVE-HANDOVER',
            'is_enabled' => false,
        ]);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData('CFS-AM-HANDOVER'));
        $memo = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-AM-HANDOVER')->sole();
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();

        $this->actingAs($lineManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Handover pending SDM to another AM/Manager (Line)')
            ->assertSee('name="line_manager_employee_number"', false)
            ->assertSee($replacementLineManager->employee_number.' - '.$replacementLineManager->name)
            ->assertDontSee($inactiveLineManager->employee_number.' - '.$inactiveLineManager->name);

        $this->actingAs($lineManager)->patch(route('sdm-memos.line-manager.update', $memo), [
            'line_manager_employee_number' => $inactiveLineManager->employee_number,
        ])->assertSessionHasErrors('line_manager_employee_number');

        $this->actingAs($lineManager)->patch(route('sdm-memos.line-manager.update', $memo), [
            'line_manager_employee_number' => $replacementLineManager->employee_number,
        ])->assertRedirect(route('sdm-memos.show', $memo));

        $assignment = $memo->approvals()->where('role', WorkflowStage::LineManager->value)->sole();
        $this->assertSame($replacementLineManager->id, $assignment->assigned_to);
        $this->assertSame($replacementLineManager->name, $assignment->assigned_name);
        $this->assertSame($replacementLineManager->employee_number, $assignment->assigned_employee_number);
        $this->assertSame('handover', $assignment->assignment_source);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $lineManager->id,
            'event' => 'memo.line_manager.handed_over',
            'auditable_id' => $memo->id,
        ]);

        $this->actingAs($replacementLineManager)->get(route('sdm-memos.index', ['category' => 'transferred']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->contains('id', $memo->id));
    }

    public function test_line_manager_cannot_handover_an_sdm_that_is_not_pending_with_them(): void
    {
        [$stationUser, $stationManager, $assignedLineManager] = $this->workflowUsers();
        $otherLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-OTHER-HANDOVER']);
        $replacementLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-HANDOVER-TARGET']);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData('CFS-AM-UNAUTHORIZED'));
        $memo = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-AM-UNAUTHORIZED')->sole();
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $assignedLineManager->employee_number,
        ])->assertRedirect();

        $this->actingAs($otherLineManager)->patch(route('sdm-memos.line-manager.update', $memo), [
            'line_manager_employee_number' => $replacementLineManager->employee_number,
        ])->assertForbidden();

        $this->assertSame(
            $assignedLineManager->id,
            $memo->approvals()->where('role', WorkflowStage::LineManager->value)->value('assigned_to'),
        );
    }

    public function test_line_manager_can_handover_a_version_two_workflow_sdm(): void
    {
        [, , $lineManager, , , $station] = $this->workflowUsers();
        $replacementLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-V2-HANDOVER']);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-AM-V2-HANDOVER',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_name' => 'Red Line',
            'line_code' => 'RED',
            'status' => MemoStatus::LineReview->value,
            'workflow_version' => 2,
            'created_by' => $lineManager->id,
            'updated_by' => $lineManager->id,
            'submitted_at' => now(),
        ]);
        $lineReviewStep = $memo->workflowSteps()->create([
            'round' => 1,
            'sequence' => 2,
            'stage' => WorkflowStepType::LineReview->value,
            'role' => UserRole::AssistantManager->value,
            'assigned_to' => $lineManager->id,
            'assigned_name' => $lineManager->name,
            'assigned_employee_number' => $lineManager->employee_number,
            'decision' => 'pending',
        ]);
        $memo->update(['current_workflow_step_id' => $lineReviewStep->id]);

        $this->actingAs($lineManager)->patch(route('sdm-memos.line-manager.update', $memo), [
            'line_manager_employee_number' => $replacementLineManager->employee_number,
        ])->assertRedirect(route('sdm-memos.show', $memo));

        $lineReviewStep->refresh();
        $this->assertSame($replacementLineManager->id, $lineReviewStep->assigned_to);
        $this->assertSame('handover', $lineReviewStep->assignment_source);
    }

    public function test_station_manager_cannot_handover_an_sdm_that_is_not_pending_with_them(): void
    {
        [$stationUser, $assignedStationManager] = $this->workflowUsers();
        $otherStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-OTHER-HANDOVER',
        ]);
        $replacementStationManager = $this->user(UserRole::StationManager, [
            'employee_number' => 'SM-HANDOVER-TARGET',
        ]);
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData(),
            'station_manager_employee_number' => $assignedStationManager->employee_number,
        ]);
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($otherStationManager)->patch(route('sdm-memos.station-manager.update', $memo), [
            'station_manager_employee_number' => $replacementStationManager->employee_number,
        ])->assertForbidden();

        $this->assertSame(
            $assignedStationManager->id,
            $memo->approvals()->where('role', WorkflowStage::StationManager->value)->value('assigned_to'),
        );
        $this->assertDatabaseMissing('audit_logs', ['event' => 'memo.station_manager.handed_over']);
    }

    public function test_custom_station_controller_role_can_see_the_change_forwarded_sm_action(): void
    {
        [$stationUser, $stationManager, , , , $station] = $this->workflowUsers();
        $role = Role::findOrCreate('station_controller');
        $role->givePermissionTo([
            UserPermission::CreateMemos->value,
            UserPermission::ViewOwnMemos->value,
            UserPermission::UpdateOwnDraftMemos->value,
            UserPermission::SubmitOwnDraftMemos->value,
        ]);
        $customController = User::factory()->create(['station_id' => $station->id]);
        $customController->assignRole($role->name);

        $this->actingAs($customController)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData('CFS-CUSTOM-CONTROLLER'),
            'station_manager_employee_number' => $stationManager->employee_number,
        ])->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->where('created_by', $customController->id)->sole();
        $this->actingAs($customController)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Change forwarded SM');

        $this->assertNotSame($stationUser->id, $customController->id);
    }

    public function test_station_manager_must_enter_an_enabled_am_employee_number_when_forwarding(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $selectedLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-SELECTED']);
        $inactiveLineManager = $this->user(UserRole::AssistantManager, [
            'employee_number' => 'AM-INACTIVE',
            'is_enabled' => false,
        ]);
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Action required: Station Manager')
            ->assertSee('name="forward_to_employee_number"', false)
            ->assertSee('x-data="employeeAutocomplete"', false)
            ->assertSee($lineManager->employee_number.' - '.$lineManager->name)
            ->assertSee($selectedLineManager->employee_number.' - '.$selectedLineManager->name)
            ->assertDontSee($inactiveLineManager->employee_number.' - '.$inactiveLineManager->name)
            ->assertSee('value="forward"', false)
            ->assertSee('data-sdm-confirm', false)
            ->assertSee('Are you sure you want to proceed?')
            ->assertSee('Cancel SDM');

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])
            ->assertSessionHasErrors('forward_to_employee_number');
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $selectedLineManager->employee_number,
        ])->assertRedirect();

        $this->assertSame($selectedLineManager->id, $memo->approvals()->where('role', WorkflowStage::LineManager->value)->value('assigned_to'));
        $this->assertNotSame($lineManager->id, $selectedLineManager->id);
    }

    public function test_station_manager_can_only_request_cancellation_and_am_line_can_cancel_with_mandatory_remarks(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'cancel',
            'remarks' => 'Cancelled after checking the station record.',
        ])->assertSessionHasErrors('action');
        $this->assertSame(MemoStatus::Submitted, $memo->fresh()->status);
        $this->assertSame(WorkflowStage::StationManager, $memo->fresh()->current_stage);

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'request_cancellation',
            'forward_to_employee_number' => $lineManager->employee_number,
            'remarks' => 'Please cancel after reviewing the station record.',
        ])->assertRedirect();
        $this->actingAs($lineManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('name="action" value="cancel" data-sdm-confirm data-confirmation-action="Cancel SDM"', false);
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'cancel'])
            ->assertSessionHasErrors('remarks');
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'cancel',
            'remarks' => 'AM/Line cancelled after review.',
        ])->assertRedirect();
        $this->actingAs($stationUser)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Cancelled by AM/Line');
    }

    public function test_station_manager_reporting_line_can_be_changed_without_station_mapping(): void
    {
        [, $stationManager] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);
        $otherLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-2']);

        $this->actingAs($administrator)->put(route('hierarchies.update'), [
            'subordinate_id' => $stationManager->id,
            'supervisor_id' => $otherLineManager->id,
        ])->assertRedirect();

        $this->assertSame($otherLineManager->id, ReportingRelationship::query()->where('subordinate_id', $stationManager->id)->value('supervisor_id'));
    }

    public function test_reporting_relationship_rejects_a_supervisor_from_the_wrong_level(): void
    {
        [, , $lineManager, , $dgm] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)->put(route('hierarchies.update'), [
            'subordinate_id' => $lineManager->id,
            'supervisor_id' => $dgm->id,
        ])->assertSessionHasErrors('supervisor_id');

        $this->assertNotSame($dgm->id, ReportingRelationship::query()->where('subordinate_id', $lineManager->id)->value('supervisor_id'));
    }

    public function test_operations_manager_cancellation_requires_a_reason_and_accepts_evidence(): void
    {
        Storage::fake('local');
        [$stationUser, , , $operationsManager] = $this->workflowUsers();

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($memo->approvals()->where('role', WorkflowStage::StationManager->value)->firstOrFail()->assignee)
            ->post(route('sdm-memos.workflow.store', $memo), [
                'action' => 'forward',
                'forward_to_employee_number' => $memo->approvals()->where('role', WorkflowStage::LineManager->value)->value('assigned_employee_number'),
            ])
            ->assertRedirect();
        $this->actingAs($memo->approvals()->where('role', WorkflowStage::LineManager->value)->firstOrFail()->assignee)
            ->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])
            ->assertRedirect();

        $this->actingAs($operationsManager)
            ->post(route('sdm-memos.workflow.store', $memo), ['action' => 'cancel'])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($operationsManager)
            ->post(route('sdm-memos.workflow.store', $memo), [
                'action' => 'cancel',
                'remarks' => 'The supporting record disproves the memo.',
                'evidence' => [UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf')],
            ])->assertRedirect(route('sdm-memos.show', $memo));

        $this->assertSame(MemoStatus::Cancelled, $memo->fresh()->status);
        $attachment = $memo->approvals()->where('role', WorkflowStage::OperationsManager->value)->sole()->attachments()->sole();
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->actingAs($operationsManager)->get(route('sdm-memos.workflow-evidence.download', [$memo, $attachment]))->assertOk();
    }

    public function test_only_the_current_snapshotted_assignee_can_act(): void
    {
        [$stationUser, , $lineManager] = $this->workflowUsers();
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($lineManager)
            ->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])
            ->assertForbidden();

        $this->assertSame(MemoStatus::Submitted, $memo->fresh()->status);
    }

    public function test_navigation_badge_counts_only_memos_waiting_for_the_current_approver(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager] = $this->workflowUsers();
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationManager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-memo-notification-count="1"', false);
        $this->actingAs($lineManager)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-memo-notification-count', false);
        $this->actingAs($operationsManager)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-memo-notification-count', false);

        $this->actingAs($stationManager)
            ->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward', 'forward_to_employee_number' => $lineManager->employee_number])
            ->assertRedirect();

        $this->actingAs($stationManager)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-memo-notification-count', false);
        $this->actingAs($lineManager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-memo-notification-count="1"', false);
    }

    public function test_sidebar_all_memos_acknowledges_only_the_current_sessions_notification_count(): void
    {
        [$firstStationUser, $firstStationManager] = $this->workflowUsers();
        $this->actingAs($firstStationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData('CFS-FIRST-SM-INBOX'),
            'station_manager_employee_number' => $firstStationManager->employee_number,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $firstMemo = ServiceDeficiencyMemo::query()->sole();

        [$secondStationUser, $secondStationManager] = $this->workflowUsers();
        $this->actingAs($secondStationUser)->post(route('sdm-memos.store-and-submit'), [
            ...$this->validMemoData('CFS-SECOND-SM-INBOX'),
            'station_manager_employee_number' => $secondStationManager->employee_number,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $secondMemo = ServiceDeficiencyMemo::query()->whereKeyNot($firstMemo->id)->sole();

        $acknowledgementUrl = route('sdm-memos.index', ['acknowledge_notifications' => 1]);

        $this->actingAs($firstStationManager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-memo-notification-count="1"', false)
            ->assertSee($acknowledgementUrl, false);

        $this->get($acknowledgementUrl)
            ->assertOk()
            ->assertSessionHas('memo_notifications_acknowledged_by', $firstStationManager->id)
            ->assertDontSee('data-memo-notification-count', false)
            ->assertSee($firstMemo->serial_number)
            ->assertSee($secondMemo->serial_number)
            ->assertViewHas('memos', function ($memos) use ($firstMemo, $secondMemo): bool {
                $memoIds = collect($memos->items())->pluck('id');

                return $memoIds->contains($firstMemo->id) && $memoIds->contains($secondMemo->id);
            });

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-memo-notification-count', false);

        $this->flushSession();

        $this->actingAs($firstStationManager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-memo-notification-count="1"', false);
    }

    public function test_administrator_can_create_station_manager_without_station_mapping(): void
    {
        [, $stationManager] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)->get(route('hierarchies.index'))->assertOk()->assertDontSee('Stations → Station Managers');
        $this->actingAs($stationManager)->get(route('hierarchies.index'))->assertForbidden();

        $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'Replacement Station Manager',
            'email' => 'replacement-sm@example.com',
            'role' => UserRole::StationManager->value,
            'employee_number' => 'SM-REPLACEMENT',
            'phone' => null,
            'designation' => 'Station Manager',
            'is_enabled' => '1',
            'password' => 'Secure Password 123!',
            'password_confirmation' => 'Secure Password 123!',
        ])->assertRedirect();

        $replacement = User::query()->where('email', 'replacement-sm@example.com')->sole();
        $this->assertTrue($replacement->hasRole(UserRole::StationManager->value));
        $this->assertNull($replacement->station_id);
    }

    public function test_hierarchy_changes_apply_only_to_new_submissions(): void
    {
        [$stationUser, $originalStationManager, $lineManager, $operationsManager, $dgm, $station] = $this->workflowUsers();
        $replacement = $this->user(UserRole::ManagerOperations, ['employee_number' => 'MO-2']);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData('CFS-SNAPSHOT-1'));
        $first = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-SNAPSHOT-1')->sole();

        ReportingRelationship::query()->updateOrCreate(
            ['subordinate_id' => $lineManager->id],
            ['supervisor_id' => $replacement->id],
        );
        ReportingRelationship::query()->create([
            'subordinate_id' => $replacement->id,
            'supervisor_id' => $dgm->id,
        ]);
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData('CFS-SNAPSHOT-2'));
        $second = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-SNAPSHOT-2')->sole();

        $this->assertSame($operationsManager->id, $first->approvals()->where('role', WorkflowStage::OperationsManager->value)->value('assigned_to'));
        $this->assertSame($replacement->id, $second->approvals()->where('role', WorkflowStage::OperationsManager->value)->value('assigned_to'));
    }

    public function test_dgm_can_cancel_with_a_reason_without_an_evidence_file(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm] = $this->workflowUsers();
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward', 'forward_to_employee_number' => $lineManager->employee_number])->assertRedirect();
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect();
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect();
        $memo->refresh();

        $this->actingAs($dgm)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('name="action" value="cancel" data-sdm-confirm data-confirmation-action="Cancel SDM"', false);

        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'cancel'])->assertSessionHasErrors('remarks');
        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'cancel', 'remarks' => 'Final authority rejected the SDM.'])->assertRedirect();
        $this->assertSame(MemoStatus::Cancelled, $memo->fresh()->status);
    }

    public function test_user_with_the_current_pending_step_cannot_be_disabled(): void
    {
        [$stationUser, $stationManager] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());

        $this->actingAs($administrator)
            ->patch(route('users.status', $stationManager), ['is_enabled' => false])
            ->assertSessionHasErrors('user');

        $this->assertTrue($stationManager->fresh()->is_enabled);
    }

    private function workflowUsers(bool $completeHierarchy = true): array
    {
        $suffix = (string) (User::query()->count() + 1);
        $locationSuffix = Station::query()->exists() ? '-'.$suffix : '';
        $line = Line::query()->create(['code' => 'RED'.$locationSuffix, 'name' => 'Red Line'.$locationSuffix, 'is_active' => true]);
        $station = Station::query()->create(['code' => 'RED'.$locationSuffix.'-01', 'name' => 'Red Station'.$locationSuffix, 'line_id' => $line->id, 'is_active' => true]);
        $stationUser = $this->user(UserRole::Station, ['station_id' => $station->id]);
        $stationManager = $this->user(UserRole::StationManager, ['employee_number' => 'SM-'.$suffix]);
        $lineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-'.$suffix]);
        $operationsManager = $this->user(UserRole::ManagerOperations, ['employee_number' => 'MO-'.$suffix]);
        $dgm = $this->user(UserRole::DeputyGeneralManager, ['employee_number' => 'DGM-'.$suffix]);

        if ($completeHierarchy) {
            ReportingRelationship::query()->create(['subordinate_id' => $stationManager->id, 'supervisor_id' => $lineManager->id]);
            ReportingRelationship::query()->create(['subordinate_id' => $lineManager->id, 'supervisor_id' => $operationsManager->id]);
            ReportingRelationship::query()->create(['subordinate_id' => $operationsManager->id, 'supervisor_id' => $dgm->id]);
        }

        return [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm, $station];
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    private function validMemoData(string $serial = 'CFS-WORKFLOW-1'): array
    {
        $contractor = Contractor::query()->firstOrCreate(['name' => 'Metro Services'], ['is_active' => true]);

        return [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => $serial,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '10:30',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-10',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->latest('id')->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Other service deficiency.'],
        ];
    }
}
