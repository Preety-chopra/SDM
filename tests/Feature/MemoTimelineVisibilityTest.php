<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
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
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MemoTimelineVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_sm_timeline_stops_at_sm_review_while_pending_with_an_sm(int $version): void
    {
        [$memo, $sm] = $this->pendingMemo($version);

        $this->actingAs($sm)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertViewHas($version === 2 ? 'timelineSteps' : 'timelineApprovals', fn ($steps): bool => $steps->pluck('sequence')->all() === [1])
            ->assertDontSee(WorkflowStepType::LineReview->label());

        $this->actingAs($this->user(UserRole::StationManager))->get(route('sdm-memos.show', $memo))->assertForbidden();
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_sm_timeline_includes_am_review_after_sm_forwards_and_hides_later_levels(int $version): void
    {
        [$memo, $sm, $am] = $this->pendingMemo($version);
        $this->actingAs($sm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $am->employee_number,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertViewHas($version === 2 ? 'timelineSteps' : 'timelineApprovals', fn ($steps): bool => $steps->pluck('sequence')->all() === [1, 2])
            ->assertDontSee(WorkflowStepType::AmCmcToScrutiny->label());

        $this->actingAs($this->user(UserRole::StationManager))->get(route('sdm-memos.show', $memo))->assertForbidden();
    }

    public function test_opening_the_sdm_expands_the_timeline_for_visible_workflow_remarks(): void
    {
        [$memo, $sm, $am] = $this->pendingMemo(2);
        $remarks = "Review <station record>\nCheck the second page.";
        $this->actingAs($sm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $am->employee_number,
            'remarks' => $remarks,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('<details data-case-timeline data-default-collapsed="false" open', false)
            ->assertSee($remarks)
            ->assertDontSee($remarks, false);
    }

    public function test_sdm_details_display_all_saved_incident_remarks(): void
    {
        [$memo, $sm] = $this->pendingMemo(2);
        $memo->update(['station_record_brief_remarks' => 'Station record remark.']);
        $memo->deficiencies()->create(['type' => DeficiencyType::Other->value, 'remarks' => 'Deficiency remark.']);
        $memo->cashCheck()->create(['remarks' => 'Cash-check remark.']);
        $memo->publicComplaint()->create([
            'complaint_serial_number' => 'PC-1',
            'station_id' => $memo->station_id,
            'station_name' => $memo->station_name,
            'remarks' => 'Complaint remark <follow-up>.',
        ]);

        $this->actingAs($sm)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee(['Station record remark.', 'Deficiency remark.', 'Cash-check remark.', 'Complaint remark <follow-up>.'])
            ->assertDontSee('Complaint remark <follow-up>.', false);
    }

    /** @return array{ServiceDeficiencyMemo, User, User} */
    private function pendingMemo(int $version): array
    {
        $line = Line::query()->create(['code' => 'TIMELINE', 'name' => 'Timeline Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'TL-01', 'name' => 'Timeline Station', 'line_id' => $line->id, 'is_active' => true]);
        $creator = $this->user(UserRole::Station);
        $sm = $this->user(UserRole::StationManager);
        $am = $this->user(UserRole::AssistantManager);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'SM-TIMELINE',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_code' => $line->code,
            'line_name' => $line->name,
            'status' => $version === 2 ? MemoStatus::AwaitingStationManager->value : MemoStatus::Submitted->value,
            'workflow_version' => $version,
            'current_stage' => WorkflowStage::StationManager->value,
            'created_by' => $creator->id,
            'submitted_at' => now(),
        ]);
        foreach ([WorkflowStage::StationManager, WorkflowStage::LineManager, WorkflowStage::OperationsManager] as $index => $stage) {
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
                    'stage' => [WorkflowStepType::StationManager, WorkflowStepType::LineReview, WorkflowStepType::AmCmcToScrutiny][$index]->value,
                ]);
                if ($index === 0) {
                    $memo->update(['current_workflow_step_id' => $step->id]);
                }
            } else {
                $memo->approvals()->create($attributes);
            }
        }

        return [$memo, $sm, $am];
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['employee_number' => 'TL-'.(User::query()->count() + 1)]);
        $user->assignRole($role->value);

        return $user;
    }
}
