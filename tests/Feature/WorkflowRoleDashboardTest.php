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
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkflowRoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-23 12:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $line = Line::query()->create(['code' => 'L-1E', 'name' => 'Red Line', 'is_active' => true]);
        $this->station = Station::query()->create(['code' => 'RED-01', 'name' => 'Central Station', 'line_id' => $line->id, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_am_cmc_dashboard_contains_only_sdms_routed_to_that_user(): void
    {
        $amCmc = $this->user(UserRole::ManagerOperations);
        $otherAmCmc = $this->user(UserRole::ManagerOperations);

        $pending = $this->memo('AM-CMC-PENDING', MemoStatus::AmCmcReview, SdmType::Cfs, '2026-09-05 09:00:00');
        $completed = $this->memo('AM-CMC-COMPLETED', MemoStatus::CmcAssignment, SdmType::Ticketing, '2026-09-10 09:00:00');
        $other = $this->memo('OTHER-AM-CMC', MemoStatus::AmCmcReview, SdmType::WandW, '2026-09-11 09:00:00');

        $this->step($pending, $amCmc, WorkflowStepType::AmCmcToScrutiny, 'pending');
        $this->step($completed, $amCmc, WorkflowStepType::AmCmcToScrutiny, 'forwarded');
        $this->step($completed, $amCmc, WorkflowStepType::AmCmcToDgm, 'forwarded');
        $this->step($other, $otherAmCmc, WorkflowStepType::AmCmcToScrutiny, 'pending');

        $this->actingAs($amCmc)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('workflowRoleDashboard', function (array $dashboard): bool {
                return $dashboard['title'] === 'AM/CMC Dashboard'
                    && $dashboard['total_received'] === 2
                    && $dashboard['pending_count'] === 1
                    && $dashboard['completed_count'] === 1
                    && $dashboard['service_distribution']['counts'] === [1, 1, 0, 0]
                    && count($dashboard['recent_sdms']) === 2;
            })
            ->assertSee('AM/CMC Dashboard')
            ->assertSee('Select a bar to view its SDMs.')
            ->assertSee('chartMemosUrl', false)
            ->assertSee('Select an SDM below to open its full details.')
            ->assertDontSee('OTHER-AM-CMC')
            ->assertDontSee($pending->serial_number)
            ->assertDontSee($completed->serial_number);
    }

    public function test_cmc_staff_dashboard_uses_the_assigned_cmc_portfolio(): void
    {
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $otherCmcStaff = $this->user(UserRole::CmcStaff);

        $pending = $this->memo('CMC-PENDING', MemoStatus::CmcScrutiny, SdmType::Cfs, '2026-09-06 09:00:00', $cmcStaff);
        $lifecycle = $this->memo('CMC-LIFECYCLE', MemoStatus::ObjectionWindow, SdmType::WandW, '2026-09-12 09:00:00', $cmcStaff);
        $other = $this->memo('OTHER-CMC', MemoStatus::CmcScrutiny, SdmType::Ticketing, '2026-09-13 09:00:00', $otherCmcStaff);

        $this->step($pending, $cmcStaff, WorkflowStepType::CmcAssessment, 'pending');
        $this->step($lifecycle, $cmcStaff, WorkflowStepType::CmcAssessment, 'forwarded');
        $this->step($other, $otherCmcStaff, WorkflowStepType::CmcAssessment, 'pending');

        $this->actingAs($cmcStaff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('workflowRoleDashboard', fn (array $dashboard): bool => $dashboard['title'] === 'CMC Staff Dashboard'
                && $dashboard['total_received'] === 2
                && $dashboard['pending_count'] === 1
                && $dashboard['completed_count'] === 1)
            ->assertSee('CMC Staff Dashboard')
            ->assertDontSee('OTHER-CMC')
            ->assertDontSee($pending->serial_number)
            ->assertDontSee($lifecycle->serial_number);
    }

    public function test_cmc_sm_dashboard_contains_only_sdms_routed_to_that_user(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead);
        $otherCmcSm = $this->user(UserRole::CmcTeamLead);

        $pending = $this->memo('CMC-SM-PENDING', MemoStatus::CmcAssignment, SdmType::Cfs, '2026-09-06 09:00:00');
        $assigned = $this->memo('CMC-SM-ASSIGNED', MemoStatus::CmcScrutiny, SdmType::WandW, '2026-09-12 09:00:00');
        $other = $this->memo('OTHER-CMC-SM', MemoStatus::CmcAssignment, SdmType::Ticketing, '2026-09-13 09:00:00');

        $this->step($pending, $cmcSm, WorkflowStepType::CmcAssignment, 'pending');
        $this->step($assigned, $cmcSm, WorkflowStepType::CmcAssignment, 'forwarded');
        $this->step($other, $otherCmcSm, WorkflowStepType::CmcAssignment, 'pending');

        $this->actingAs($cmcSm)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('workflowRoleDashboard', fn (array $dashboard): bool => $dashboard['title'] === 'CMC SM Dashboard'
                && $dashboard['total_received'] === 2
                && $dashboard['pending_count'] === 1
                && $dashboard['completed_count'] === 1
                && $dashboard['service_distribution']['counts'] === [1, 0, 1, 0])
            ->assertSee('CMC SM Dashboard')
            ->assertSee('Select a bar to view its SDMs.')
            ->assertSee('chartMemosUrl', false)
            ->assertDontSee('OTHER-CMC-SM')
            ->assertDontSee($pending->serial_number)
            ->assertDontSee($assigned->serial_number);
    }

    public function test_cmc_sm_can_load_only_their_sdms_for_a_selected_status_bar(): void
    {
        $cmcSm = $this->user(UserRole::CmcTeamLead);
        $otherCmcSm = $this->user(UserRole::CmcTeamLead);
        $selected = $this->memo('SELECTED-CMC-SM', MemoStatus::CmcAssignment, SdmType::Cfs, '2026-09-05 09:00:00');
        $otherStatus = $this->memo('OTHER-CMC-SM-STATUS', MemoStatus::CmcScrutiny, SdmType::Cfs, '2026-09-06 09:00:00');
        $otherUser = $this->memo('OTHER-CMC-SM-USER', MemoStatus::CmcAssignment, SdmType::Cfs, '2026-09-07 09:00:00');

        $this->step($selected, $cmcSm, WorkflowStepType::CmcAssignment, 'pending');
        $this->step($otherStatus, $cmcSm, WorkflowStepType::CmcAssignment, 'forwarded');
        $this->step($otherUser, $otherCmcSm, WorkflowStepType::CmcAssignment, 'pending');

        $this->actingAs($cmcSm)
            ->getJson(route('dashboard.chart-sdms', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
                'status' => MemoStatus::CmcAssignment->value,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'memos')
            ->assertJsonPath('memos.0.id', $selected->id)
            ->assertJsonMissingPath('memos.0.serial_number')
            ->assertJsonPath('memos.0.url', route('sdm-memos.show', $selected));
    }

    public function test_dgm_cmc_dashboard_contains_only_sdms_routed_to_that_user(): void
    {
        $dgm = $this->user(UserRole::DeputyGeneralManager);
        $otherDgm = $this->user(UserRole::DeputyGeneralManager);

        $pending = $this->memo('DGM-PENDING', MemoStatus::DgmApproval, SdmType::Ticketing, '2026-09-07 09:00:00');
        $approved = $this->memo('DGM-APPROVED', MemoStatus::PendingPenaltyLetter, SdmType::Cfs, '2026-09-14 09:00:00');
        $other = $this->memo('OTHER-DGM', MemoStatus::DgmApproval, SdmType::WandW, '2026-09-15 09:00:00');

        $this->step($pending, $dgm, WorkflowStepType::DgmApproval, 'pending');
        $this->step($approved, $dgm, WorkflowStepType::DgmApproval, 'approved');
        $this->step($other, $otherDgm, WorkflowStepType::DgmApproval, 'pending');

        $this->actingAs($dgm)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('workflowRoleDashboard', fn (array $dashboard): bool => $dashboard['title'] === 'DGM/CMC Dashboard'
                && $dashboard['total_received'] === 2
                && $dashboard['pending_count'] === 1
                && $dashboard['completed_count'] === 1)
            ->assertSee('DGM/CMC Dashboard')
            ->assertDontSee('OTHER-DGM')
            ->assertDontSee($pending->serial_number)
            ->assertDontSee($approved->serial_number);
    }

    public function test_workflow_user_can_load_only_their_sdms_for_a_selected_status_bar(): void
    {
        $amCmc = $this->user(UserRole::ManagerOperations);
        $otherAmCmc = $this->user(UserRole::ManagerOperations);
        $selected = $this->memo('SELECTED-AM-CMC', MemoStatus::AmCmcReview, SdmType::Cfs, '2026-09-05 09:00:00');
        $otherStatus = $this->memo('OTHER-STATUS', MemoStatus::CmcAssignment, SdmType::Cfs, '2026-09-06 09:00:00');
        $otherUser = $this->memo('OTHER-USER-AM-CMC', MemoStatus::AmCmcReview, SdmType::Cfs, '2026-09-07 09:00:00');

        $this->step($selected, $amCmc, WorkflowStepType::AmCmcToScrutiny, 'pending');
        $this->step($otherStatus, $amCmc, WorkflowStepType::AmCmcToScrutiny, 'forwarded');
        $this->step($otherUser, $otherAmCmc, WorkflowStepType::AmCmcToScrutiny, 'pending');

        $this->actingAs($amCmc)
            ->getJson(route('dashboard.chart-sdms', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
                'status' => MemoStatus::AmCmcReview->value,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'memos')
            ->assertJsonPath('memos.0.id', $selected->id)
            ->assertJsonMissingPath('memos.0.serial_number')
            ->assertJsonPath('memos.0.url', route('sdm-memos.show', $selected));
    }

    public function test_unsupported_dashboard_role_cannot_load_chart_sdms(): void
    {
        $user = $this->user(UserRole::StationManager);

        $this->actingAs($user)
            ->getJson(route('dashboard.chart-sdms'))
            ->assertForbidden();
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function memo(
        string $serialNumber,
        MemoStatus $status,
        SdmType $serviceType,
        string $submittedAt,
        ?User $cmcStaff = null,
    ): ServiceDeficiencyMemo {
        return ServiceDeficiencyMemo::query()->create([
            'service_type' => $serviceType->value,
            'serial_number' => $serialNumber,
            'station_id' => $this->station->id,
            'station_name' => 'Central Station',
            'line_code' => 'L-1E',
            'line_name' => 'Red Line',
            'status' => $status->value,
            'workflow_version' => 2,
            'cmc_staff_id' => $cmcStaff?->id,
            'submitted_at' => $submittedAt,
        ]);
    }

    private function step(ServiceDeficiencyMemo $memo, User $assignee, WorkflowStepType $stage, string $decision): void
    {
        $step = MemoWorkflowStep::query()->create([
            'memo_id' => $memo->id,
            'round' => 1,
            'sequence' => match ($stage) {
                WorkflowStepType::AmCmcToScrutiny => 3,
                WorkflowStepType::CmcAssessment => 5,
                WorkflowStepType::AmCmcToDgm => 6,
                WorkflowStepType::DgmApproval => 7,
                default => 1,
            },
            'stage' => $stage->value,
            'role' => $stage->role()->value,
            'assigned_to' => $assignee->id,
            'assigned_name' => $assignee->name,
            'decision' => $decision,
            'acted_by' => $decision === 'pending' ? null : $assignee->id,
            'acted_at' => $decision === 'pending' ? null : $memo->submitted_at?->copy()->addDay(),
        ]);

        if ($decision === 'pending') {
            $memo->update(['current_workflow_step_id' => $step->id]);
        }
    }
}
