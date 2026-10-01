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

class LineManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-23 12:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_line_manager_dashboard_scopes_received_sdms_and_summarizes_workflow_outcomes(): void
    {
        $manager = $this->lineManager('AM-100');
        $otherManager = $this->lineManager('AM-200');
        [$blueStation, $redStation, $greyStation, $airportStation] = $this->stations();

        $this->assignedMemo($manager, $blueStation, 'AM-PENDING', MemoStatus::LineReview, 'pending', '2026-09-04 09:00:00');
        $this->assignedMemo($manager, $blueStation, 'AM-FORWARDED-1', MemoStatus::AmCmcReview, 'forwarded', '2026-09-08 09:00:00');
        $this->assignedMemo($manager, $redStation, 'AM-FORWARDED-2', MemoStatus::CmcAssignment, 'forwarded', '2026-09-12 09:00:00', SdmType::Ticketing);
        $this->assignedMemo($manager, $redStation, 'AM-CANCELLED', MemoStatus::Cancelled, 'cancelled', '2026-09-16 09:00:00', SdmType::WandW);
        $this->assignedMemo($manager, $airportStation, 'AM-AEL', MemoStatus::AmCmcReview, 'forwarded', '2026-09-17 09:00:00');
        $this->assignedMemo($manager, $greyStation, 'AM-LINE-9', MemoStatus::AmCmcReview, 'forwarded', '2026-09-18 09:00:00');
        $this->assignedMemo($otherManager, $redStation, 'OTHER-AM', MemoStatus::LineReview, 'pending', '2026-09-18 09:00:00');

        $this->actingAs($manager)
            ->get(route('dashboard', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
            ->assertOk()
            ->assertViewHas('isLineManager', true)
            ->assertViewHas('lineManagerDashboard', function (array $dashboard): bool {
                return $dashboard['total_received'] === 6
                    && $dashboard['pending_count'] === 1
                    && $dashboard['forwarded_count'] === 4
                    && $dashboard['cancelled_count'] === 1
                    && $dashboard['line_distribution']['labels'] === ['L-1E', 'L-3E&4', 'L-9', 'AEL']
                    && $dashboard['line_distribution']['counts'] === [2, 2, 1, 1]
                    && $dashboard['line_distribution']['colors'] === ['#CD5356', '#0d6efd', '#A4A4A4', '#A4A4A4']
                    && count($dashboard['recent_sdms']) === 6;
            })
            ->assertSee('AM/Manager (Line) Dashboard')
            ->assertSee('Total received')
            ->assertSee('Forwarded onward')
            ->assertSee('Line-wise received SDMs')
            ->assertSee('Select a bar to view its SDMs.')
            ->assertSee('lineMemosUrl', false)
            ->assertSee('Select an SDM below to open its full details.')
            ->assertSee('>L-1E</td>', false)
            ->assertSee('>L-3E&amp;4</td>', false)
            ->assertSee('>L-9</td>', false)
            ->assertSee('>AEL</td>', false)
            ->assertDontSee('OTHER-AM');

        $this->assertSame('#0d6efd', Line::query()->where('code', 'L-3E&4')->value('color'));
        $this->assertSame('#CD5356', Line::query()->where('code', 'L-1E')->value('color'));
    }

    public function test_line_manager_dashboard_has_six_month_service_type_trends_and_filtering(): void
    {
        $manager = $this->lineManager('AM-100');
        [$blueStation] = $this->stations();

        $this->assignedMemo($manager, $blueStation, 'CFA-APRIL', MemoStatus::AmCmcReview, 'forwarded', '2026-04-05 09:00:00');
        $this->assignedMemo($manager, $blueStation, 'CFA-SEPTEMBER', MemoStatus::LineReview, 'pending', '2026-09-05 09:00:00');
        $this->assignedMemo($manager, $blueStation, 'TVS-AUGUST', MemoStatus::AmCmcReview, 'forwarded', '2026-08-05 09:00:00', SdmType::Ticketing);

        $this->actingAs($manager)
            ->get(route('dashboard', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
                'service_type' => SdmType::Cfs->value,
            ]))
            ->assertOk()
            ->assertViewHas('lineManagerDashboard', function (array $dashboard): bool {
                $trend = $dashboard['monthly_trend'];

                return $dashboard['total_received'] === 1
                    && $dashboard['pending_count'] === 1
                    && $dashboard['filters']['service_type'] === SdmType::Cfs->value
                    && $trend['labels'] === ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep']
                    && $trend['datasets'][0]['service_type'] === SdmType::Cfs->value
                    && $trend['datasets'][0]['data'] === [1, 0, 0, 0, 0, 1];
            })
            ->assertDontSee('TVS-AUGUST');
    }

    public function test_line_manager_current_status_filter_contains_only_am_and_downstream_statuses(): void
    {
        $manager = $this->lineManager('AM-100');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('lineManagerDashboard', fn (array $dashboard): bool => $dashboard['statuses'] === [
                ['value' => MemoStatus::LineReview->value, 'label' => 'Pending (at current user level)'],
                ['value' => MemoStatus::AmCmcReview->value, 'label' => 'AM CMC Review'],
                ['value' => MemoStatus::CmcAssignment->value, 'label' => 'CMC Assignment'],
                ['value' => MemoStatus::CmcScrutiny->value, 'label' => 'CMC Scrutiny'],
                ['value' => MemoStatus::DgmApproval->value, 'label' => 'DGM Approval'],
                ['value' => MemoStatus::PendingPenaltyLetter->value, 'label' => 'Pending Penalty Letter'],
                ['value' => MemoStatus::ObjectionWindow->value, 'label' => 'Objection Window'],
                ['value' => MemoStatus::ObjectionReview->value, 'label' => 'Objection Review'],
                ['value' => MemoStatus::Cancelled->value, 'label' => 'Cancelled'],
                ['value' => MemoStatus::Closed->value, 'label' => 'Closed'],
            ])
            ->assertSee('Pending (at current user level)')
            ->assertDontSee('value="draft"', false)
            ->assertDontSee('>Draft</option>', false)
            ->assertDontSee('value="pending_deduction"', false);
    }

    public function test_line_manager_dashboard_defaults_to_the_current_half_month(): void
    {
        $manager = $this->lineManager('AM-100');
        [$station] = $this->stations();

        $this->assignedMemo($manager, $station, 'FIRST-HALF', MemoStatus::LineReview, 'pending', '2026-09-15 23:59:59');
        $this->assignedMemo($manager, $station, 'SECOND-HALF', MemoStatus::LineReview, 'pending', '2026-09-16 00:00:00');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('lineManagerDashboard', fn (array $dashboard): bool => $dashboard['total_received'] === 1
                && $dashboard['filters']['date_from'] === '2026-09-16'
                && $dashboard['filters']['date_to'] === '2026-09-30'
                && $dashboard['recent_sdms']->pluck('id')->all() === [ServiceDeficiencyMemo::query()->where('serial_number', 'SECOND-HALF')->value('id')]);

        Carbon::setTestNow('2026-09-10 12:00:00');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('lineManagerDashboard', fn (array $dashboard): bool => $dashboard['total_received'] === 1
                && $dashboard['filters']['date_from'] === '2026-09-01'
                && $dashboard['filters']['date_to'] === '2026-09-15'
                && $dashboard['recent_sdms']->pluck('id')->all() === [ServiceDeficiencyMemo::query()->where('serial_number', 'FIRST-HALF')->value('id')]);
    }

    public function test_known_line_colors_are_saved_on_the_lines_table(): void
    {
        $palette = [
            'RED' => '#CD5356',
            'YELLOW' => '#ffc107',
            'BLUE' => '#0d6efd',
            'GREEN' => '#198754',
            'VIOLET' => '#6610f2',
            'PINK' => '#EE9ACD',
            'MAGENTA' => '#F75CF7',
            'GREY' => '#A4A4A4',
            'AEL' => '#A4A4A4',
        ];

        foreach ($palette as $code => $color) {
            $line = Line::query()->create([
                'code' => $code,
                'name' => $code === 'AEL' ? 'Airport Express Line' : $code.' Line',
                'is_active' => true,
            ]);

            $this->assertSame($color, $line->color);
            $this->assertSame($color, Line::query()->findOrFail($line->id)->color);
        }
    }

    public function test_line_manager_can_load_only_their_sdms_for_a_selected_line(): void
    {
        $manager = $this->lineManager('AM-100');
        $otherManager = $this->lineManager('AM-200');
        [$blueStation, $redStation] = $this->stations();

        $selectedMemo = $this->assignedMemo(
            $manager,
            $blueStation,
            'AM-BLUE',
            MemoStatus::LineReview,
            'pending',
            '2026-09-04 09:00:00',
        );
        $this->assignedMemo($manager, $redStation, 'AM-RED', MemoStatus::LineReview, 'pending', '2026-09-05 09:00:00');
        $this->assignedMemo($otherManager, $blueStation, 'OTHER-AM-BLUE', MemoStatus::LineReview, 'pending', '2026-09-06 09:00:00');

        $this->actingAs($manager)
            ->getJson(route('dashboard.line-sdms', [
                'line_code' => 'L-3E&4',
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertJsonPath('line_code', 'L-3E&4')
            ->assertJsonCount(1, 'memos')
            ->assertJsonPath('memos.0.id', $selectedMemo->id)
            ->assertJsonMissingPath('memos.0.serial_number')
            ->assertJsonPath('memos.0.url', route('sdm-memos.show', $selectedMemo));
    }

    public function test_non_line_manager_cannot_load_line_dashboard_sdms(): void
    {
        $stationUser = User::factory()->create();
        $stationUser->assignRole(UserRole::Station->value);

        $this->actingAs($stationUser)
            ->getJson(route('dashboard.line-sdms', ['line_code' => 'L-1E']))
            ->assertForbidden();
    }

    private function lineManager(string $employeeNumber): User
    {
        $manager = User::factory()->create(['employee_number' => $employeeNumber]);
        $manager->assignRole(UserRole::AssistantManager->value);

        return $manager;
    }

    /** @return array{Station, Station, Station, Station} */
    private function stations(): array
    {
        $blue = Line::query()->create(['code' => 'L-3E&4', 'name' => 'Blue Line', 'is_active' => true]);
        $red = Line::query()->create(['code' => 'L-1E', 'name' => 'Red Line', 'is_active' => true]);
        $grey = Line::query()->create(['code' => 'L-9', 'name' => 'Grey Line', 'is_active' => true]);
        $airport = Line::query()->create(['code' => 'AEL', 'name' => 'Airport Express Line', 'is_active' => true]);

        return [
            Station::query()->create(['code' => 'BLUE-01', 'name' => 'Central Station', 'line_id' => $blue->id, 'is_active' => true]),
            Station::query()->create(['code' => 'RED-01', 'name' => 'South Station', 'line_id' => $red->id, 'is_active' => true]),
            Station::query()->create(['code' => 'GREY-01', 'name' => 'Grey Station', 'line_id' => $grey->id, 'is_active' => true]),
            Station::query()->create(['code' => 'AEL-01', 'name' => 'Airport Station', 'line_id' => $airport->id, 'is_active' => true]),
        ];
    }

    private function assignedMemo(
        User $manager,
        Station $station,
        string $serialNumber,
        MemoStatus $status,
        string $decision,
        string $submittedAt,
        SdmType $serviceType = SdmType::Cfs,
    ): ServiceDeficiencyMemo {
        $line = Line::query()->findOrFail($station->line_id);
        $creator = User::factory()->create(['station_id' => $station->id]);
        $creator->assignRole(UserRole::Station->value);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => $serviceType->value,
            'serial_number' => $serialNumber,
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_code' => $line->code,
            'line_name' => $line->name,
            'status' => $status->value,
            'workflow_version' => 2,
            'created_by' => $creator->id,
            'submitted_at' => $submittedAt,
        ]);
        $step = MemoWorkflowStep::query()->create([
            'memo_id' => $memo->id,
            'round' => 1,
            'sequence' => 2,
            'stage' => WorkflowStepType::LineReview->value,
            'role' => UserRole::AssistantManager->value,
            'assigned_to' => $manager->id,
            'assigned_name' => $manager->name,
            'assigned_employee_number' => $manager->employee_number,
            'decision' => $decision,
            'acted_by' => $decision === 'pending' ? null : $manager->id,
            'acted_at' => $decision === 'pending' ? null : Carbon::parse($submittedAt)->addDay(),
        ]);

        if ($decision === 'pending') {
            $memo->update(['current_workflow_step_id' => $step->id]);
        }

        return $memo;
    }
}
