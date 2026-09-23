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

class StationManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 12:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_station_manager_dashboard_shows_only_sdms_submitted_to_the_signed_in_manager(): void
    {
        $manager = $this->stationManager('SM-100');
        $otherManager = $this->stationManager('SM-200');
        [$central, $south] = $this->stations();

        $this->assignedMemo($manager, $central, 'SM-PENDING', MemoStatus::AwaitingStationManager, 'pending', '2026-09-05 09:00:00');
        $this->assignedMemo($manager, $central, 'SM-FORWARDED-1', MemoStatus::LineReview, 'forwarded', '2026-09-10 09:00:00');
        $this->assignedMemo($manager, $south, 'SM-FORWARDED-2', MemoStatus::AmCmcReview, 'forwarded', '2026-09-15 09:00:00');
        $this->assignedMemo($otherManager, $south, 'OTHER-MANAGER', MemoStatus::AwaitingStationManager, 'pending', '2026-09-16 09:00:00');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('isStationManager', true)
            ->assertViewHas('stationManagerDashboard', function (array $dashboard): bool {
                return $dashboard['total_submitted'] === 3
                    && $dashboard['pending_count'] === 1
                    && $dashboard['forwarded_count'] === 2
                    && $dashboard['station_distribution']['labels'] === ['Central Station', 'South Station']
                    && $dashboard['station_distribution']['counts'] === [2, 1]
                    && count($dashboard['recent_sdms']) === 3;
            })
            ->assertSee('Station Manager Dashboard')
            ->assertSee('Pending with me')
            ->assertSee('Forwarded to AM/Line')
            ->assertSee('Station-wise SDMs')
            ->assertSee('SM-PENDING')
            ->assertSee('SM-FORWARDED-2')
            ->assertDontSee('OTHER-MANAGER');
    }

    public function test_station_manager_dashboard_filters_all_metrics_and_records(): void
    {
        $manager = $this->stationManager('SM-100');
        [$central, $south] = $this->stations();

        $this->assignedMemo($manager, $central, 'CFS-PENDING', MemoStatus::AwaitingStationManager, 'pending', '2026-09-05 09:00:00', SdmType::Cfs);
        $this->assignedMemo($manager, $south, 'TVS-FORWARDED', MemoStatus::LineReview, 'forwarded', '2026-09-10 09:00:00', SdmType::Ticketing);

        $this->actingAs($manager)
            ->get(route('dashboard', ['service_type' => SdmType::Cfs->value]))
            ->assertOk()
            ->assertViewHas('stationManagerDashboard', function (array $dashboard): bool {
                return $dashboard['total_submitted'] === 1
                    && $dashboard['pending_count'] === 1
                    && $dashboard['forwarded_count'] === 0
                    && $dashboard['filters']['service_type'] === SdmType::Cfs->value;
            })
            ->assertSee('CFS-PENDING')
            ->assertDontSee('TVS-FORWARDED');
    }

    public function test_station_manager_trend_contains_one_monthly_line_per_submitted_sdm_type_for_the_latest_six_months(): void
    {
        $manager = $this->stationManager('SM-100');
        [$central] = $this->stations();

        $this->assignedMemo($manager, $central, 'CFS-APRIL', MemoStatus::LineReview, 'forwarded', '2026-04-05 09:00:00', SdmType::Cfs);
        $this->assignedMemo($manager, $central, 'CFS-SEPTEMBER', MemoStatus::LineReview, 'forwarded', '2026-09-05 09:00:00', SdmType::Cfs);
        $this->assignedMemo($manager, $central, 'TVS-AUGUST', MemoStatus::LineReview, 'forwarded', '2026-08-05 09:00:00', SdmType::Ticketing);
        $this->assignedMemo($manager, $central, 'OUTSIDE-SIX-MONTHS', MemoStatus::LineReview, 'forwarded', '2026-03-31 23:59:59', SdmType::Marshall);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stationManagerDashboard', function (array $dashboard): bool {
                $trend = $dashboard['monthly_trend'];
                $datasets = collect($trend['datasets'])->keyBy('service_type');

                return $trend['labels'] === ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep']
                    && $datasets->keys()->all() === [SdmType::Cfs->value, SdmType::Ticketing->value]
                    && $datasets[SdmType::Cfs->value]['data'] === [1, 0, 0, 0, 0, 1]
                    && $datasets[SdmType::Ticketing->value]['data'] === [0, 0, 0, 0, 1, 0];
            })
            ->assertSee('Latest six months, with one line for each SDM type submitted to you.');
    }

    private function stationManager(string $employeeNumber): User
    {
        $manager = User::factory()->create(['employee_number' => $employeeNumber]);
        $manager->assignRole(UserRole::StationManager->value);

        return $manager;
    }

    /** @return array{Station, Station} */
    private function stations(): array
    {
        $line = Line::query()->create(['code' => 'BLUE', 'name' => 'Blue Line', 'is_active' => true]);

        return [
            Station::query()->create(['code' => 'CEN', 'name' => 'Central Station', 'line_id' => $line->id, 'is_active' => true]),
            Station::query()->create(['code' => 'STH', 'name' => 'South Station', 'line_id' => $line->id, 'is_active' => true]),
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
        $creator = User::factory()->create(['station_id' => $station->id]);
        $creator->assignRole(UserRole::Station->value);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => $serviceType->value,
            'serial_number' => $serialNumber,
            'station_id' => $station->id,
            'station_name' => $station->name,
            'status' => $status->value,
            'workflow_version' => 2,
            'created_by' => $creator->id,
            'submitted_at' => $submittedAt,
        ]);
        $step = MemoWorkflowStep::query()->create([
            'memo_id' => $memo->id,
            'round' => 1,
            'sequence' => 1,
            'stage' => WorkflowStepType::StationManager->value,
            'role' => UserRole::StationManager->value,
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
