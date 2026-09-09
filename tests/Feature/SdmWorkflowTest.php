<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ReportingRelationship;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\StationHierarchy;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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

    public function test_submission_is_blocked_until_the_station_hierarchy_is_complete(): void
    {
        [$stationUser, $stationManager, , , , $station] = $this->workflowUsers(false);
        StationHierarchy::query()->create([
            'station_id' => $station->id,
            'station_manager_id' => $stationManager->id,
        ]);

        $this->actingAs($stationUser)
            ->post(route('sdm-memos.store-and-submit'), $this->validMemoData())
            ->assertSessionHasErrors('hierarchy');

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame(MemoStatus::Draft, $memo->status);
        $this->assertNull($memo->submitted_at);
    }

    public function test_administrator_maps_each_approval_role_to_its_immediate_supervisor(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm, $station] = $this->workflowUsers(false);
        $administrator = $this->user(UserRole::Administrator);
        StationHierarchy::query()->create([
            'station_id' => $station->id,
            'station_manager_id' => $stationManager->id,
        ]);

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
        [, , , , , $station] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)->get(route('hierarchies.index'))
            ->assertOk()
            ->assertSee('Stations → Station Managers')
            ->assertSee('Station Managers → Assistant Managers')
            ->assertSee('Assistant Managers → Managers (Operations)')
            ->assertSee('Managers (Operations) → DGM/O')
            ->assertSee($station->name)
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
        $this->actingAs($stationManager)->get(route('sdm-memos.index'))->assertOk()->assertSee($memo->serial_number);
        $this->actingAs($lineManager)->get(route('sdm-memos.index'))->assertOk()->assertDontSee($memo->serial_number);
        $this->actingAs($operationsManager)->get(route('sdm-memos.index'))->assertOk()->assertDontSee($memo->serial_number);

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

    public function test_submission_requires_the_station_managers_employee_number(): void
    {
        [$stationUser, $stationManager] = $this->workflowUsers();
        $data = $this->validMemoData();
        unset($data['station_manager_employee_number']);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $data)
            ->assertSessionHasErrors('station_manager_employee_number');

        $data['station_manager_employee_number'] = 'UNKNOWN-SM';
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
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationUser)->get(route('sdm-memos.edit', $memo))->assertOk();
        $this->actingAs($stationUser)->post(route('sdm-memos.update', $memo), [
            ...$this->validMemoData($memo->serial_number),
            'staff_name' => 'Corrected Staff Name',
        ])->assertRedirect();
        $this->assertSame('Corrected Staff Name', $memo->fresh()->staff_name);

        Carbon::setTestNow('2026-09-03 10:00:01');
        $this->actingAs($stationUser)->get(route('sdm-memos.edit', $memo))->assertForbidden();

        Carbon::setTestNow('2026-09-01 11:00:00');
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();
        $this->actingAs($stationUser)->get(route('sdm-memos.edit', $memo))->assertForbidden();
    }

    public function test_station_manager_must_enter_an_enabled_am_employee_number_when_forwarding(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $selectedLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-SELECTED']);
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])
            ->assertSessionHasErrors('forward_to_employee_number');
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $selectedLineManager->employee_number,
        ])->assertRedirect();

        $this->assertSame($selectedLineManager->id, $memo->approvals()->where('role', WorkflowStage::LineManager->value)->value('assigned_to'));
        $this->assertNotSame($lineManager->id, $selectedLineManager->id);
    }

    public function test_sm_and_am_line_can_cancel_with_mandatory_remarks_and_all_levels_see_who_cancelled_it(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm] = $this->workflowUsers();
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData());
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'cancel'])
            ->assertSessionHasErrors('remarks');
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'cancel',
            'remarks' => 'Cancelled after checking the station record.',
            'evidence' => [UploadedFile::fake()->create('sm-proof.pdf', 10, 'application/pdf')],
        ])->assertRedirect();

        foreach ([$stationManager, $lineManager, $operationsManager, $dgm] as $viewer) {
            $this->actingAs($viewer)->get(route('sdm-memos.show', $memo))
                ->assertOk()
                ->assertSee('Cancelled by SM');
            $this->actingAs($viewer)->get(route('sdm-memos.index'))
                ->assertOk()
                ->assertSee('Cancelled by SM');
        }

        [$secondStationUser, $secondStationManager, $secondLineManager] = $this->workflowUsers();
        $this->actingAs($secondStationUser)->post(route('sdm-memos.store-and-submit'), $this->validMemoData('CFS-AM-CANCEL'));
        $amMemo = ServiceDeficiencyMemo::query()->where('serial_number', 'CFS-AM-CANCEL')->sole();
        $this->actingAs($secondStationManager)->post(route('sdm-memos.workflow.store', $amMemo), [
            'action' => 'forward',
            'forward_to_employee_number' => $secondLineManager->employee_number,
        ])->assertRedirect();
        $this->actingAs($secondLineManager)->post(route('sdm-memos.workflow.store', $amMemo), ['action' => 'cancel'])
            ->assertSessionHasErrors('remarks');
        $this->actingAs($secondLineManager)->post(route('sdm-memos.workflow.store', $amMemo), [
            'action' => 'cancel',
            'remarks' => 'AM/Line cancelled after review.',
        ])->assertRedirect();
        $this->actingAs($secondStationUser)->get(route('sdm-memos.show', $amMemo))
            ->assertOk()
            ->assertSee('Cancelled by AM/Line');
    }

    public function test_station_manager_mapping_can_be_changed_without_editing_each_station(): void
    {
        [, $stationManager, , , , $station] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);
        $otherLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-2']);

        $this->actingAs($administrator)->put(route('hierarchies.update'), [
            'subordinate_id' => $stationManager->id,
            'supervisor_id' => $otherLineManager->id,
        ])->assertRedirect();

        $this->assertSame($stationManager->id, $station->hierarchy()->value('station_manager_id'));
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

    public function test_administrator_can_configure_hierarchy_and_station_manager_selection_transfers_silently(): void
    {
        [$stationUser, $oldStationManager, $lineManager, $operationsManager, $dgm, $station] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);
        $newStationManager = $this->user(UserRole::StationManager, ['email' => 'new-sm@example.com', 'employee_number' => 'SM-NEW']);

        $this->actingAs($administrator)->get(route('hierarchies.index'))->assertOk()->assertSee($station->name);
        $this->actingAs($stationUser)->get(route('hierarchies.index'))->assertForbidden();

        $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'Replacement Station Manager',
            'email' => 'replacement-sm@example.com',
            'role' => UserRole::StationManager->value,
            'employee_number' => 'SM-REPLACEMENT',
            'phone' => null,
            'designation' => 'Station Manager',
            'station_ids' => [$station->id],
            'is_enabled' => '1',
            'password' => 'Secure Password 123!',
            'password_confirmation' => 'Secure Password 123!',
        ])->assertRedirect();

        $replacement = User::query()->where('email', 'replacement-sm@example.com')->sole();
        $this->assertSame($replacement->id, $station->hierarchy()->value('station_manager_id'));
        $this->assertNotSame($oldStationManager->id, $replacement->id);
        $this->assertNotSame($newStationManager->id, $replacement->id);
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
            StationHierarchy::query()->create([
                'station_id' => $station->id,
                'station_manager_id' => $stationManager->id,
            ]);
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
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Other service deficiency.'],
        ];
    }
}
