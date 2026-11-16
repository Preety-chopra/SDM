<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmCmcMemoTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_am_cmc_tabs_separate_line_receipts_processed_sdms_and_dgm_approvals(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create()->assignRole(UserRole::ManagerOperations->value);
        $line = Line::query()->create(['code' => 'YL', 'name' => 'Yellow Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'YL-01', 'name' => 'Central Station', 'line_id' => $line->id, 'is_active' => true]);

        $received = $this->memo($station, 'TAB-RECEIVED', MemoStatus::AmCmcReview, WorkflowStepType::AmCmcToScrutiny);
        $aft = $this->memo($station, 'TAB-AFT', MemoStatus::AmCmcReview, WorkflowStepType::AmCmcToScrutiny);
        $this->step($aft, WorkflowStepType::DgmAftReview, 'forwarded');
        $processed = $this->memo($station, 'TAB-PROCESSED', MemoStatus::AmCmcReview, WorkflowStepType::AmCmcToDgm);
        $awaitingApproval = $this->memo($station, 'TAB-AWAITING-APPROVAL', MemoStatus::DgmApproval, WorkflowStepType::DgmApproval);
        $inScrutiny = $this->memo($station, 'TAB-IN-SCRUTINY', MemoStatus::CmcScrutiny, WorkflowStepType::CmcAssessment);
        $this->step($inScrutiny, WorkflowStepType::DgmApproval, 'returned');
        $approved = $this->memo($station, 'TAB-APPROVED', MemoStatus::PendingPenaltyLetter);
        $this->step($approved, WorkflowStepType::DgmApproval, 'approved');
        $closed = $this->memo($station, 'TAB-APPROVED-AND-CLOSED', MemoStatus::Closed);
        $this->step($closed, WorkflowStepType::DgmApproval, 'approved');
        $cancelled = $this->memo($station, 'TAB-CANCELLED', MemoStatus::Cancelled);
        $this->step($cancelled, WorkflowStepType::DgmApproval, 'cancelled');
        $legacyReceived = $this->legacyMemo($station, 'TAB-LEGACY-RECEIVED', WorkflowStage::OperationsManager, 'pending');
        $legacyProcessed = $this->legacyMemo($station, 'TAB-LEGACY-PROCESSED', WorkflowStage::DeputyGeneralManager, 'pending');
        $legacyApproved = $this->legacyMemo($station, 'TAB-LEGACY-APPROVED', WorkflowStage::DeputyGeneralManager, 'approved');

        $this->actingAs($manager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSeeInOrder(['All Memos', 'SDMs Received from Lines', 'SDMs Processed by CMC', 'SDMs Approved by DGM/CMC'])
            ->assertDontSee('Pending Memos');

        $this->get(route('sdm-memos.index', ['category' => 'received_from_lines', 'line_code' => 'YL']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->sort()->values()->all() === [$received->id, $legacyReceived->id])
            ->assertSee('name="category" value="received_from_lines"', false);

        $this->get(route('sdm-memos.index', ['category' => 'processed_by_cmc']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->sort()->values()->all() === [$processed->id, $awaitingApproval->id, $legacyProcessed->id]);

        $this->get(route('sdm-memos.index', ['category' => 'approved_by_dgm_cmc']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->sort()->values()->all() === [$approved->id, $closed->id, $legacyApproved->id]);
    }

    public function test_other_roles_cannot_open_am_cmc_tabs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $lineManager = User::factory()->create()->assignRole(UserRole::AssistantManager->value);

        $this->actingAs($lineManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('Pending Memos')
            ->assertDontSee('SDMs Received from Lines');

        $this->from(route('sdm-memos.index'))
            ->get(route('sdm-memos.index', ['category' => 'received_from_lines']))
            ->assertRedirect(route('sdm-memos.index'))
            ->assertSessionHasErrors('category');
    }

    private function memo(Station $station, string $serial, MemoStatus $status, ?WorkflowStepType $stage = null): ServiceDeficiencyMemo
    {
        $memo = ServiceDeficiencyMemo::query()->create([
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_code' => 'YL',
            'serial_number' => $serial,
            'service_type' => SdmType::Cfs->value,
            'status' => $status->value,
            'workflow_version' => 2,
            'submitted_at' => now(),
        ]);

        if ($stage !== null) {
            $memo->update(['current_workflow_step_id' => $this->step($memo, $stage, 'pending')]);
        }

        return $memo;
    }

    private function step(ServiceDeficiencyMemo $memo, WorkflowStepType $stage, string $decision): int
    {
        return $memo->workflowSteps()->create([
            'round' => 1,
            'sequence' => $memo->workflowSteps()->count() + 1,
            'stage' => $stage->value,
            'role' => $stage->role()->value,
            'assigned_name' => $stage->label(),
            'decision' => $decision,
        ])->id;
    }

    private function legacyMemo(Station $station, string $serial, WorkflowStage $stage, string $decision): ServiceDeficiencyMemo
    {
        $memo = $this->memo($station, $serial, $decision === 'approved' ? MemoStatus::Closed : MemoStatus::UnderReview);
        $memo->update(['workflow_version' => 1, 'current_stage' => $stage->value]);
        $memo->approvals()->create([
            'role' => $stage->value,
            'sequence' => $stage->sequence(),
            'decision' => $decision,
        ]);

        return $memo;
    }
}
