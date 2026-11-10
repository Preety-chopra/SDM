<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AftWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $users;

    private Station $station;

    private ServiceContract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        foreach (['aft', 'dgm_aft', 'deputy_general_manager', 'manager_operations', 'cmc_team_lead', 'cmc_staff'] as $role) {
            $user = User::factory()->create(['employee_number' => strtoupper($role)]);
            $user->assignRole($role);
            $this->users[$role] = $user;
        }
        $line = Line::query()->create(['code' => 'AFT', 'name' => 'Inspection Line', 'is_active' => true]);
        $this->station = Station::query()->create(['code' => 'AFT-01', 'name' => 'Inspection Station', 'line_id' => $line->id, 'is_active' => true]);
        $contractor = Contractor::query()->create(['name' => 'Inspection Contractor', 'is_active' => true]);
        $this->contract = ServiceContract::query()->create([
            'contract_group' => str()->ulid()->toString(), 'version' => 1,
            'contractor_id' => $contractor->id, 'line_id' => $line->id, 'station_id' => $this->station->id,
            'service_type' => SdmType::Cfs->value, 'contract_number' => 'AFT/2026', 'title' => 'Inspection contract',
            'effective_from' => '2026-01-01', 'effective_to' => '2027-12-31', 'status' => 'published',
        ]);
        $this->contract->seedPenaltyClauses();
    }

    public function test_aft_report_passes_through_every_reviewer_and_rejoins_the_penalty_workflow(): void
    {
        $this->actingAs($this->users['aft'])->get(route('sdm-memos.create'))
            ->assertOk()->assertSee('Inspected station')->assertDontSee('name="station_manager_employee_number"', false);
        $memo = $this->submitReport();
        $this->assertSame(2, $memo->workflow_version);
        $this->actingAs($this->users['aft'])->get(route('sdm-memos.index'))
            ->assertOk()->assertSee($memo->serial_number);
        $this->actingAs($this->users['dgm_aft'])->get(route('dashboard'))
            ->assertOk()->assertSee('DGM AFT Dashboard')
            ->assertViewHas('workflowRoleDashboard', fn (array $dashboard) => $dashboard['pending_count'] === 1);
        $this->assertSame([
            'dgm_aft_review', 'dgm_cmc_to_review', 'am_cmc_to_scrutiny', 'cmc_assignment',
            'cmc_assessment', 'cmc_verification', 'am_cmc_to_dgm', 'dgm_cmc_approval',
        ], $memo->workflowSteps->pluck('stage')->map(fn ($stage) => $stage->value)->all());

        foreach (['dgm_aft', 'deputy_general_manager', 'manager_operations'] as $role) {
            $this->actingAs($this->users[$role])->get(route('sdm-memos.show', $memo))->assertOk();
            $this->actingAs($this->users[$role])->get(route('sdm-memos.index', ['category' => 'pending_action']))
                ->assertOk()->assertSee($memo->serial_number);
            $this->forward($memo, $role);
        }
        $this->actingAs($this->users['cmc_team_lead'])->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'assign', 'cmc_staff_id' => $this->users['cmc_staff']->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $clause = $this->contract->penaltyClauses()->firstOrFail();
        $this->forward($memo, 'cmc_staff', [
            'items' => [['contract_penalty_clause_id' => $clause->id, 'proposed_amount' => 100]],
            'proposed_total' => 100, 'negative_points' => 1, 'staff_action' => 'warning',
        ]);
        $this->forward($memo, 'cmc_team_lead');
        $this->forward($memo, 'manager_operations');
        $this->assertSame(WorkflowStepType::DgmApproval, $memo->fresh()->currentWorkflowStep->stage);
        $this->actingAs($this->users['deputy_general_manager'])->post(route('sdm-memos.workflow.store', $memo), ['action' => 'approve'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(MemoStatus::PendingPenaltyLetter, $memo->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $memo->id, 'event' => 'memo.submitted', 'actor_id' => $this->users['aft']->id]);
        foreach (['dgm_aft', 'deputy_general_manager'] as $role) {
            $this->assertDatabaseHas('audit_logs', ['auditable_id' => $memo->id, 'event' => 'memo.workflow.forward', 'actor_id' => $this->users[$role]->id]);
        }
    }

    public function test_aft_review_cannot_be_skipped_or_used_as_final_approval(): void
    {
        $memo = $this->submitReport();
        foreach (['aft', 'deputy_general_manager', 'manager_operations'] as $role) {
            $this->actingAs($this->users[$role])->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertForbidden();
        }
        $this->actingAs($this->users['dgm_aft'])->post(route('sdm-memos.workflow.store', $memo), ['action' => 'approve'])->assertSessionHasErrors('action');
        $this->forward($memo, 'dgm_aft');
        $this->actingAs($this->users['deputy_general_manager'])->post(route('sdm-memos.workflow.store', $memo), ['action' => 'approve'])->assertSessionHasErrors('action');
        $this->actingAs($this->users['dgm_aft'])->get(route('sdm-memos.create'))->assertForbidden();
    }

    public function test_aft_drafts_keep_the_inspected_station_and_submit_to_dgm_aft(): void
    {
        $this->actingAs($this->users['aft'])->post(route('sdm-memos.store'), $this->reportData())->assertSessionHasNoErrors()->assertRedirect();
        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->get(route('sdm-memos.edit', $memo))->assertOk()->assertSee($this->station->name);
        $this->post(route('sdm-memos.submit', $memo), $this->reportData())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('dgm_aft_review', $memo->fresh()->currentWorkflowStep->stage->value);
    }

    public function test_aft_requires_an_unambiguous_enabled_dgm_aft(): void
    {
        $this->users['dgm_aft']->update(['is_enabled' => false]);
        $this->actingAs($this->users['aft'])->post(route('sdm-memos.store-and-submit'), $this->reportData())->assertSessionHasErrors('workflow');
        $this->assertDatabaseMissing('service_deficiency_memos', ['workflow_version' => 2]);
    }

    public function test_aft_report_requires_staff_review_before_sm_verification(): void
    {
        $memo = $this->submitReport();
        foreach (['dgm_aft', 'deputy_general_manager', 'manager_operations'] as $role) {
            $this->forward($memo, $role);
        }
        $this->actingAs($this->users['cmc_team_lead'])->get(route('sdm-memos.show', $memo))
            ->assertOk()->assertDontSee('Verify this SDM directly');
        $this->post(route('sdm-memos.workflow.store', $memo), ['action' => 'verify'])->assertSessionHasErrors('action');
        $this->post(route('sdm-memos.workflow.store', $memo), ['action' => 'assign', 'cmc_staff_id' => $this->users['cmc_staff']->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertForbidden();
    }

    public function test_aft_station_selection_overrides_login_station_and_requires_a_matching_contract(): void
    {
        $other = Station::query()->create(['code' => 'OTHER', 'name' => 'Other station', 'line_id' => $this->station->line_id, 'is_active' => true]);
        $this->withSession(['auth.station_id' => $other->id]);
        $memo = $this->submitReport();
        $this->assertSame($this->station->id, $memo->station_id);
        $this->actingAs($this->users['aft'])->post(route('sdm-memos.store-and-submit'), [...$this->reportData(), 'station_id' => $other->id])
            ->assertSessionHasErrors('contractor_id');
        $this->assertDatabaseCount('service_deficiency_memos', 1);
    }

    public function test_aft_always_uses_contract_review_even_when_legacy_workflow_is_enabled(): void
    {
        config()->set('sdm.require_contract_workflow', false);
        $memo = $this->submitReport();
        $this->assertSame(2, $memo->workflow_version);
        $this->assertSame('dgm_aft_review', $memo->currentWorkflowStep->stage->value);
    }

    public function test_aft_creation_explains_when_no_active_stations_are_available(): void
    {
        $this->station->update(['is_active' => false]);
        $this->actingAs($this->users['aft'])->get(route('sdm-memos.create'))->assertUnprocessable();
    }

    public function test_role_upgrade_restores_permissions_preserves_assignments_and_audits_only_changes(): void
    {
        $role = Role::findByName('aft');
        $role->revokePermissionTo(UserPermission::SubmitOwnDraftMemos);
        $migration = require database_path('migrations/2026_11_09_124942_add_aft_roles_and_permissions.php');
        $migration->up();
        $this->assertTrue($this->users['aft']->fresh()->can(UserPermission::SubmitOwnDraftMemos->value));
        $this->assertTrue($this->users['aft']->fresh()->hasRole('aft'));
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.aft.configured', 'auditable_id' => $role->id]);
        $migration->up();
        $migration->down();
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertTrue($this->users['aft']->fresh()->hasRole('aft'));
    }

    public function test_administrator_can_create_aft_users_without_a_fixed_station(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);
        foreach (['aft', 'dgm_aft'] as $role) {
            $this->actingAs($administrator)->get(route('users.create', $role))->assertOk();
            $this->post(route('users.store'), [
                'name' => 'New '.str_replace('_', ' ', $role), 'email' => $role.'@example.com', 'role' => $role,
                'employee_number' => 'NEW-'.$role, 'is_enabled' => 1,
                'password' => 'Secure Password 123!', 'password_confirmation' => 'Secure Password 123!',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $created = User::query()->where('email', $role.'@example.com')->sole();
            $this->assertNull($created->station_id);
            $this->assertTrue($created->hasRole($role));
            $this->assertDatabaseHas('audit_logs', ['event' => 'user.created', 'auditable_id' => $created->id]);
        }
    }

    #[TestWith([false, false])]
    #[TestWith([false, true])]
    #[TestWith([true, false])]
    #[TestWith([true, true])]
    public function test_existing_submission_routes_remain_unchanged_with_enabled_aft_users(bool $contractWorkflow, bool $issuedByStationManager): void
    {
        config()->set('sdm.require_contract_workflow', $contractWorkflow);
        $stationManager = User::factory()->create(['employee_number' => 'COMPAT-SM']);
        $stationManager->assignRole(UserRole::StationManager);
        $lineManager = User::factory()->create(['employee_number' => 'COMPAT-LINE']);
        $lineManager->assignRole(UserRole::AssistantManager);
        $creator = $stationManager;
        if (! $issuedByStationManager) {
            $creator = User::factory()->create(['station_id' => $this->station->id]);
            $creator->assignRole(UserRole::Station);
        }

        $this->actingAs($creator)->post(route('sdm-memos.store-and-submit'), [
            ...$this->reportData(),
            'station_manager_employee_number' => $stationManager->employee_number,
            'line_manager_employee_number' => $lineManager->employee_number,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertFalse($memo->isAftInspection());
        $this->assertSame($contractWorkflow ? 2 : 1, $memo->workflow_version);

        if ($contractWorkflow) {
            $this->assertSame([
                'station_manager_signoff', 'line_penalty_review', 'am_cmc_to_scrutiny', 'cmc_assignment',
                'cmc_assessment', 'cmc_verification', 'am_cmc_to_dgm', 'dgm_cmc_approval',
            ], $memo->workflowSteps->pluck('stage')->map(fn ($stage) => $stage->value)->all());
            $this->assertSame($issuedByStationManager ? 'line_penalty_review' : 'station_manager_signoff', $memo->currentWorkflowStep->stage->value);
        } else {
            $this->assertSame([
                'station_manager', 'manager_assistant_manager_line', 'manager_operations', 'dgm_operations',
            ], $memo->approvals->pluck('role')->map(fn ($stage) => $stage->value)->all());
            $this->assertSame($issuedByStationManager ? 'manager_assistant_manager_line' : 'station_manager', $memo->current_stage->value);
        }

        if (! $issuedByStationManager) {
            $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
                'action' => 'forward', 'forward_to_employee_number' => $lineManager->employee_number,
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            ...($contractWorkflow ? ['items' => [[
                'contract_penalty_clause_id' => $this->contract->penaltyClauses()->firstOrFail()->id,
                'proposed_amount' => 100,
            ]]] : []),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->forward($memo, 'manager_operations');

        if ($contractWorkflow) {
            $this->assertSame(WorkflowStepType::CmcAssignment, $memo->fresh()->currentWorkflowStep->stage);
            $this->actingAs($this->users['cmc_team_lead'])->get(route('sdm-memos.show', $memo))
                ->assertOk()->assertSee('Verify this SDM directly');
            $this->post(route('sdm-memos.workflow.store', $memo), ['action' => 'verify'])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($this->users['cmc_team_lead']->id, $memo->fresh()->currentWorkflowStep->assigned_to);
        } else {
            $this->actingAs($this->users['deputy_general_manager'])->post(route('sdm-memos.workflow.store', $memo), ['action' => 'approve'])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame(MemoStatus::Closed, $memo->fresh()->status);
        }
    }

    private function submitReport(): ServiceDeficiencyMemo
    {
        $this->actingAs($this->users['aft'])->post(route('sdm-memos.store-and-submit'), $this->reportData())
            ->assertSessionHasNoErrors()->assertRedirect();

        return ServiceDeficiencyMemo::query()->sole();
    }

    private function reportData(): array
    {
        return [
            'station_id' => $this->station->id, 'contractor_id' => $this->contract->contractor_id,
            'service_type' => SdmType::Cfs->value, 'incident_date' => '09-11-2026', 'incident_time' => '10:30',
            'staff_name' => 'Inspected staff', 'employee_number' => 'STAFF-1', 'outsourced_staff_acknowledged' => 1,
            'deficiencies' => [DeficiencyType::DressCode->value],
            'deficiency_remarks' => [DeficiencyType::DressCode->value => 'Uniform incomplete during random inspection.'],
        ];
    }

    private function forward(ServiceDeficiencyMemo $memo, string $role, array $data = []): void
    {
        $this->actingAs($this->users[$role])->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward', ...$data])
            ->assertSessionHasNoErrors()->assertRedirect();
    }
}
