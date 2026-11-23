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

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_remarks_section_displays_each_hierarchy_levels_remarks_and_author_above_history(int $version): void
    {
        [$memo] = $this->pendingMemo($version);
        $stages = $version === 2 ? WorkflowStepType::cases() : WorkflowStage::cases();
        $expected = [];
        foreach ($stages as $index => $stage) {
            $author = $this->user($stage->role());
            $author->update(['name' => 'Remark author <'.$index.'>']);
            $remark = "Review <level {$index}>\nPlease verify the evidence.";
            $attributes = [
                'assigned_to' => $author->id,
                'assigned_name' => 'Different assignee',
                'acted_by' => $author->id,
                'acted_at' => now(),
                'decision' => ['forwarded', 'returned', 'approved'][$index % 3],
                'remarks' => $remark,
            ];
            if ($version === 2) {
                $memo->workflowSteps()->updateOrCreate(['sequence' => $index + 1], [
                    ...$attributes, 'round' => $index < 8 ? 1 : 2,
                    'stage' => $stage->value, 'role' => $stage->role()->value,
                    'actor_name' => $author->name,
                ]);
            } else {
                $memo->approvals()->updateOrCreate(['sequence' => $index + 1], [
                    ...$attributes, 'role' => $stage->value, 'signer_name' => $author->name,
                ]);
            }
            $expected[] = [$stage->label(), $author->name, $remark];
        }

        $response = $this->actingAs($this->user(UserRole::Administrator))->get(route('sdm-memos.show', $memo));

        $response->assertOk()->assertSeeInOrder([
            'SDM remarks', $expected[0][2], $version === 2 ? 'Case timeline' : 'Approval history',
        ]);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//section[@aria-label="SDM remarks" and not(ancestor::details)]//li');
        $this->assertCount(count($expected), $rows);
        foreach ($expected as $index => [$label, $author, $remark]) {
            $row = $rows->item($index)->textContent;
            $this->assertStringContainsString($label, $row);
            $this->assertStringContainsString($author, $row);
            $this->assertStringContainsString($remark, $row);
            $response->assertDontSee($remark, false)->assertDontSee($author, false);
        }
    }

    public function test_remarks_section_preserves_legacy_return_remarks_with_the_original_authors(): void
    {
        [$memo, $sm, $am] = $this->pendingMemo(1);
        $this->actingAs($sm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward', 'forward_to_employee_number' => $am->employee_number,
            'remarks' => 'Original SM forwarding remark.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($am)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return', 'remarks' => 'Please correct the station record.',
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($sm)->get(route('sdm-memos.show', $memo));

        $response->assertOk()->assertSeeInOrder([
            'SDM remarks', $sm->name, 'Original SM forwarding remark.',
            $am->name, 'Please correct the station record.', 'Approval history',
        ]);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_remarks_section_respects_hierarchy_visibility_and_omits_blank_remarks(int $version): void
    {
        [$memo, $sm] = $this->pendingMemo($version);
        $relation = $version === 2 ? $memo->workflowSteps() : $memo->approvals();
        $relation->where('sequence', 1)->update(['remarks' => '   ']);
        $relation->where('sequence', 3)->update(['remarks' => 'Restricted Manager CMC remark.']);

        $this->actingAs($sm)->get(route('sdm-memos.show', $memo))
            ->assertOk()->assertDontSee('aria-label="SDM remarks"', false)
            ->assertDontSee('Restricted Manager CMC remark.');
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
