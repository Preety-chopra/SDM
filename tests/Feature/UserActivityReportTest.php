<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Enums\WorkflowStepType;
use App\Models\MemoApproval;
use App\Models\MemoWorkflowStep;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivityReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_hr_summary_counts_created_sdms_and_forwarding_at_each_level_without_double_counting_memos(): void
    {
        $creator = User::factory()->create(['name' => 'Station creator', 'employee_number' => 'HR-001']);
        $reviewer = User::factory()->create(['name' => 'CMC reviewer', 'employee_number' => 'HR-002']);
        $memo = $this->memo($creator);
        $this->memo($creator, ['status' => 'draft', 'submitted_at' => null]);
        $this->step($memo, $reviewer, WorkflowStepType::AmCmcToScrutiny);
        $this->step($memo, $reviewer, WorkflowStepType::AmCmcToDgm);
        $this->step($memo, $reviewer, WorkflowStepType::AmCmcToDgm, 'forwarded', 2);
        $this->step($memo, $reviewer, WorkflowStepType::DgmApproval, 'approved');
        $this->step($memo, $reviewer, WorkflowStepType::CmcAssessment, 'returned');
        $this->step($memo, $reviewer, WorkflowStepType::LineReview, 'cancelled');
        $this->step($memo, $reviewer, WorkflowStepType::StationManager, 'pending');

        $response = $this->asAdmin()->get(route('user-activity.index'))->assertOk()
            ->assertSee('HR activity summary')->assertSee('Forwarding by level')->assertSee('Employee contribution')
            ->assertSee('HR-001')->assertSee('HR-002')->assertSee('Activity history')->assertSee('Recorded user activity');
        $report = $response->viewData('hrSummary');
        $this->assertSame(['created' => 2, 'forwarded' => 1, 'forward_actions' => 3, 'approved' => 1], $report['totals']);
        $this->assertSame(2, $report['levels'][WorkflowStepType::AmCmcToDgm->value]['forward_actions']);
        $this->assertSame(1, $report['levels'][WorkflowStepType::AmCmcToDgm->value]['forwarded']);
        $this->assertSame(2, $report['people']->firstWhere('id', $creator->id)['created']);
        $this->assertSame(1, $report['people']->firstWhere('id', $reviewer->id)['forwarded']);
        $this->assertSame(3, $report['people']->firstWhere('id', $reviewer->id)['forward_actions']);
    }

    public function test_hr_summary_filters_by_actual_actor_and_action_date_independently_of_history_filters_and_page(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $oldMemo = $this->memo($other, ['created_at' => '2026-10-01 12:00:00']);
        $this->memo($actor, ['created_at' => '2026-11-01 23:59:59']);
        $this->memo($actor, ['created_at' => '2026-10-31 23:59:59']);
        $this->memo($actor, ['created_at' => '2026-11-02 00:00:00']);
        $this->step($oldMemo, $actor, WorkflowStepType::CmcAssessment, at: '2026-11-01 23:59:59');
        $oldMemo->workflowSteps()->firstOrFail()->update(['assigned_to' => $other->id]);
        $this->step($oldMemo, $actor, WorkflowStepType::CmcAssessment, round: 2, at: '2026-11-02 00:00:00');
        $this->step($oldMemo, $actor, WorkflowStepType::CmcAssessment, round: 3, at: '2026-10-31 23:59:59');
        $this->step($oldMemo, $other, WorkflowStepType::AmCmcToScrutiny);

        $response = $this->asAdmin()->get(route('user-activity.index', [
            'actor_id' => $actor->id, 'from' => '2026-11-01', 'to' => '2026-11-01',
            'event' => 'auth.login', 'search' => 'unmatched-history-search', 'page' => 2,
        ]))->assertOk()->assertSee('No activity matches these filters.');

        $report = $response->viewData('hrSummary');
        $this->assertSame(['created' => 1, 'forwarded' => 1, 'forward_actions' => 1, 'approved' => 0], $report['totals']);
        $this->assertCount(1, $report['people']);
        $this->assertSame($actor->id, $report['people']->first()['id']);
    }

    public function test_hr_summary_includes_legacy_workflow_and_keeps_dgm_approvals_separate_from_forwards(): void
    {
        $actor = User::factory()->create();
        $memo = $this->memo($actor, ['workflow_version' => 1]);
        foreach (WorkflowStage::cases() as $stage) {
            MemoApproval::query()->create([
                'memo_id' => $memo->id, 'role' => $stage->value, 'sequence' => $stage->sequence(),
                'decision' => 'approved', 'acted_by' => $actor->id, 'acted_at' => '2026-11-01 12:00:00',
            ]);
        }

        $report = $this->asAdmin()->get(route('user-activity.index'))->assertOk()->viewData('hrSummary');

        $this->assertSame(['created' => 1, 'forwarded' => 1, 'forward_actions' => 3, 'approved' => 1], $report['totals']);
        $this->assertSame(1, $report['levels'][WorkflowStepType::StationManager->value]['forwarded']);
        $this->assertSame(1, $report['levels'][WorkflowStepType::LineReview->value]['forwarded']);
        $this->assertSame(1, $report['levels']['legacy_manager_operations']['forwarded']);
        $this->assertSame(0, $report['levels'][WorkflowStepType::DgmApproval->value]['forwarded']);
    }

    public function test_empty_summary_and_unattributed_activity_are_understandable_and_user_names_are_escaped(): void
    {
        $this->asAdmin()->get(route('user-activity.index'))->assertOk()
            ->assertSee('No SDM creation or completed forwarding activity matches the selected user and dates.')
            ->assertViewHas('hrSummary', fn ($report) => $report['totals'] === [
                'created' => 0, 'forwarded' => 0, 'forward_actions' => 0, 'approved' => 0,
            ]);
        $actor = User::factory()->create(['name' => '<script>hr()</script>']);
        $this->memo($actor);
        $memo = $this->memo(null);
        $memo->delete();

        $this->get(route('user-activity.index'))->assertOk()
            ->assertSee('System / deleted user')->assertSee('<script>hr()</script>')
            ->assertDontSee('<script>hr()</script>', false)
            ->assertViewHas('hrSummary', fn ($report) => $report['totals']['created'] === 2);
    }

    private function asAdmin(): static
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Administrator->value);

        return $this->actingAs($admin);
    }

    private function memo(?User $creator, array $attributes = []): ServiceDeficiencyMemo
    {
        $station = Station::query()->firstOrCreate(['code' => 'HR-ST'], ['name' => 'HR Station']);

        return ServiceDeficiencyMemo::query()->forceCreate([
            'station_id' => $station->id, 'service_type' => 'customer_facilitation', 'status' => 'submitted',
            'created_by' => $creator?->id, 'workflow_version' => 2,
            'created_at' => '2026-11-01 12:00:00', 'submitted_at' => '2026-11-01 12:00:00',
            ...$attributes,
        ]);
    }

    private function step(ServiceDeficiencyMemo $memo, User $actor, WorkflowStepType $stage, string $decision = 'forwarded', int $round = 1, string $at = '2026-11-01 12:00:00'): void
    {
        MemoWorkflowStep::query()->create([
            'memo_id' => $memo->id, 'stage' => $stage->value, 'role' => $stage->role()->value,
            'assigned_to' => $actor->id, 'assigned_name' => $actor->name,
            'sequence' => $memo->workflowSteps()->count() + 1, 'round' => $round,
            'decision' => $decision, 'acted_by' => $decision === 'pending' ? null : $actor->id,
            'acted_at' => $decision === 'pending' ? null : $at,
        ]);
    }
}
