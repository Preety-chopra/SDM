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
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('incompleteStaffDetails')]
    public function test_sm_cannot_remove_required_staff_details_from_a_submitted_sdm(int $version, bool $forwarded, array $staffDetails, array $errors): void
    {
        [$memo, $sm, $am] = $this->memoAwaitingStationManager($version);
        if ($forwarded) {
            $this->forward($memo, $sm, $am);
        }
        $status = $memo->refresh()->status;
        $data = array_replace($this->editData(), $staffDetails);
        foreach ($staffDetails as $field => $value) {
            if ($value === null) {
                unset($data[$field]);
            }
        }

        $this->actingAs($sm)->from(route('sdm-memos.edit', $memo))
            ->post(route('sdm-memos.update', $memo), $data)
            ->assertRedirect(route('sdm-memos.edit', $memo))
            ->assertSessionHasErrors($errors);

        $this->assertDatabaseHas('service_deficiency_memos', [
            'id' => $memo->id,
            'staff_name' => 'Original staff',
            'employee_number' => 'OUT-ORIGINAL',
            'status' => $status->value,
        ]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'memo.station_manager.edited']);
    }

    public static function incompleteStaffDetails(): iterable
    {
        foreach ([1, 2] as $version) {
            foreach ([false, true] as $forwarded) {
                $stage = $forwarded ? 'after forwarding' : 'before forwarding';
                foreach ([
                    'blank name' => [['staff_name' => ''], ['staff_name' => 'The staff name field is required.']],
                    'blank number' => [['employee_number' => ''], ['employee_number' => 'The employee number field is required.']],
                    'both blank' => [['staff_name' => '', 'employee_number' => ''], ['staff_name', 'employee_number']],
                    'whitespace' => [['staff_name' => '   ', 'employee_number' => '   '], ['staff_name', 'employee_number']],
                    'omitted' => [['staff_name' => null, 'employee_number' => null], ['staff_name', 'employee_number']],
                ] as $case => [$details, $errors]) {
                    yield "v{$version} {$stage}: {$case}" => [$version, $forwarded, $details, $errors];
                }
            }
        }
    }

    #[DataProvider('incompleteMandatoryDetails')]
    public function test_sm_cannot_remove_any_mandatory_details_from_a_submitted_sdm(int $version, bool $forwarded, array $changes, array $errors): void
    {
        [$memo, $sm, $am] = $this->memoAwaitingStationManager($version);
        if ($forwarded) {
            $this->forward($memo, $sm, $am);
        }
        $before = $memo->refresh()->getAttributes();
        $data = array_replace($this->editData(), $changes);
        foreach ($changes as $field => $value) {
            if ($value === null) {
                unset($data[$field]);
            }
        }

        $this->actingAs($sm)->from(route('sdm-memos.edit', $memo))
            ->post(route('sdm-memos.update', $memo), $data)
            ->assertRedirect(route('sdm-memos.edit', $memo))
            ->assertSessionHasErrors($errors);

        $this->assertSame($before, $memo->refresh()->getAttributes());
        $this->assertSame(DeficiencyType::Other, $memo->deficiencies()->sole()->type);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'memo.station_manager.edited']);
    }

    public static function incompleteMandatoryDetails(): iterable
    {
        $cases = [
            'SDM type' => [['service_type' => ''], ['service_type']],
            'contractor' => [['contractor_id' => ''], ['contractor_id']],
            'incident date' => [['incident_date' => ''], ['incident_date']],
            'incident time' => [['incident_time' => ''], ['incident_time']],
            'deficiencies' => [['deficiencies' => []], ['deficiencies']],
            'acknowledgement' => [['outsourced_staff_acknowledged' => '0'], [
                'outsourced_staff_acknowledged' => 'Confirm that you have received acknowledgement from outsourced staff.',
            ]],
            'omitted details' => [[
                'contractor_id' => null, 'incident_date' => null, 'incident_time' => null,
                'deficiencies' => null, 'outsourced_staff_acknowledged' => null,
            ], ['contractor_id', 'incident_date', 'incident_time', 'deficiencies', 'outsourced_staff_acknowledged']],
            'empty cash check' => [[
                'service_type' => SdmType::Ticketing->value,
                'deficiencies' => [DeficiencyType::CashCheck->value],
                'cash_check' => [],
            ], ['cash_check' => 'Enter at least one TOM / CCC cash-check detail or remark.']],
            'complaint station' => [[
                'deficiencies' => [DeficiencyType::PublicComplaint->value],
                'public_complaint' => ['number' => 'COMPLAINT-1', 'station_id' => ''],
            ], ['public_complaint.station_id']],
        ];
        foreach (['booked_shift_from', 'booked_shift_to', 'actual_reporting_time'] as $field) {
            $late = ['booked_shift_from' => '08:00', 'booked_shift_to' => '16:00', 'actual_reporting_time' => '08:15'];
            $late[$field] = '';
            $cases[$field] = [[
                'deficiencies' => [DeficiencyType::LateReporting->value],
                'late_reporting' => $late,
            ], ['late_reporting.'.$field]];
        }
        foreach (['check_date' => ['check_time' => '10:00'], 'check_time' => ['check_date' => '2026-08-01']] as $field => $cash) {
            $cases[$field] = [[
                'service_type' => SdmType::Ticketing->value,
                'deficiencies' => [DeficiencyType::CashCheck->value],
                'cash_check' => $cash,
            ], ['cash_check.'.$field]];
        }
        foreach ([1, 2] as $version) {
            foreach ([false, true] as $forwarded) {
                $stage = $forwarded ? 'after forwarding' : 'before forwarding';
                foreach ($cases as $case => [$changes, $errors]) {
                    yield "v{$version} {$stage}: {$case}" => [$version, $forwarded, $changes, $errors];
                }
            }
        }
    }

    #[TestWith([1, 'late_reporting'])]
    #[TestWith([2, 'late_reporting'])]
    #[TestWith([1, 'cash_check'])]
    #[TestWith([2, 'cash_check'])]
    public function test_sm_can_save_complete_conditional_details_without_submission_recipient_fields(int $version, string $type): void
    {
        [$memo, $sm] = $this->memoAwaitingStationManager($version);
        $data = [
            ...$this->editData(),
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [$type],
            'late_reporting' => ['booked_shift_from' => '08:00', 'booked_shift_to' => '16:00', 'actual_reporting_time' => '08:15'],
            'cash_check' => ['remarks' => 'Cash check completed.'],
        ];

        $this->actingAs($sm)->post(route('sdm-memos.update', $memo), $data)
            ->assertSessionHasNoErrors()->assertRedirect(route('sdm-memos.show', $memo));

        $this->assertSame($type, $memo->deficiencies()->sole()->type->value);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_sm_can_correct_staff_details_and_forward_the_submitted_sdm(int $version): void
    {
        [$memo, $sm, $am] = $this->memoAwaitingStationManager($version);

        $this->actingAs($sm)->post(route('sdm-memos.update', $memo), $this->editData())
            ->assertSessionHasNoErrors()->assertRedirect(route('sdm-memos.show', $memo));
        $this->forward($memo, $sm, $am);

        $this->assertDatabaseHas('service_deficiency_memos', [
            'id' => $memo->id,
            'staff_name' => 'Corrected after forwarding',
            'employee_number' => 'OUT-CORRECTED',
            'status' => $version === 2 ? MemoStatus::LineReview->value : MemoStatus::UnderReview->value,
        ]);
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
        $contractor = Contractor::query()->create(['name' => 'AM Open Contractor', 'is_active' => true]);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'AM-FIRST-OPEN',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_name' => $line->name,
            'line_code' => $line->code,
            'staff_name' => 'Original staff',
            'employee_number' => 'OUT-ORIGINAL',
            'contractor_id' => $contractor->id,
            'incident_date' => '2026-08-18',
            'incident_time' => '10:30',
            'status' => $version === 2 ? MemoStatus::AwaitingStationManager->value : MemoStatus::Submitted->value,
            'workflow_version' => $version,
            'current_stage' => WorkflowStage::StationManager->value,
            'created_by' => $creator->id,
            'submitted_at' => now(),
        ]);
        $memo->deficiencies()->create(['type' => DeficiencyType::Other->value]);
        if ($version === 2) {
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
            'contractor_id' => Contractor::query()->sole()->id,
            'incident_date' => '2026-08-18',
            'incident_time' => '10:30',
            'staff_name' => $staffName,
            'employee_number' => 'OUT-CORRECTED',
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Updated before AM opened.'],
        ];
    }
}
