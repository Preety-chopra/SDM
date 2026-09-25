<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StationControllerDashboardTest extends TestCase
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

    public function test_authenticated_station_user_can_access_the_dashboard_with_scoped_totals_and_charts(): void
    {
        [$controller, $station] = $this->stationController();
        $otherController = User::factory()->create(['station_id' => $station->id]);
        $otherController->assignRole(UserRole::Station->value);

        $this->memo($controller, $station, 'OWN-CFA', SdmType::Cfs, MemoStatus::AwaitingStationManager, '2026-09-03 08:00:00');
        $this->memo($controller, $station, 'OWN-TVS', SdmType::Ticketing, MemoStatus::LineReview, '2026-09-12 09:00:00');
        $this->memo($controller, $station, 'OWN-CFA-2', SdmType::Cfs, MemoStatus::AmCmcReview, '2026-09-18 10:00:00');
        $this->memo($controller, $station, 'PREVIOUS-MONTH', SdmType::WandW, MemoStatus::Closed, '2026-08-31 23:59:59');
        $this->memo($otherController, $station, 'SOMEONE-ELSE', SdmType::Marshall, MemoStatus::AwaitingStationManager, '2026-09-15 10:00:00');

        $this->actingAs($controller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('isStationController', true)
            ->assertViewHas('stationControllerDashboard', function (array $dashboard): bool {
                return $dashboard['total_sdms'] === 3
                    && $dashboard['status_counts'][MemoStatus::AwaitingStationManager->value] === 1
                    && $dashboard['status_counts'][MemoStatus::LineReview->value] === 2
                    && $dashboard['status_counts'][MemoStatus::AmCmcReview->value] === 0
                    && $dashboard['service_counts'][SdmType::Cfs->value] === 2
                    && $dashboard['service_counts'][SdmType::Ticketing->value] === 1
                    && collect($dashboard['workflow_by_service']['datasets'])->pluck('label')->all() === ['Pending with SM', 'Processed by SM']
                    && count($dashboard['monthly_trend']['labels']) === 6
                    && count($dashboard['recent_sdms']) === 3
                    && $dashboard['recent_sdms']->firstWhere('status', MemoStatus::AmCmcReview)?->dashboard_status === MemoStatus::LineReview->value;
            })
            ->assertSee('My SDM Dashboard')
            ->assertSee('SDMs filed over time')
            ->assertSee('SM-level status by service')
            ->assertSee('Pending aging')
            ->assertSee('Select a bar to view its SDMs.')
            ->assertSee('chartMemosUrl', false)
            ->assertSee('Select an SDM below to open its full details.')
            ->assertSee('Processed by SM')
            ->assertDontSee('AM CMC Review')
            ->assertDontSee('OWN-CFA')
            ->assertDontSee('PREVIOUS-MONTH')
            ->assertDontSee('SOMEONE-ELSE');
    }

    public function test_dashboard_defaults_to_current_month_and_date_range_filter_is_inclusive(): void
    {
        [$controller, $station] = $this->stationController();

        $this->memo($controller, $station, 'START-BOUNDARY', SdmType::Cfs, MemoStatus::Draft, '2026-09-10 00:00:00');
        $this->memo($controller, $station, 'END-BOUNDARY', SdmType::Ticketing, MemoStatus::Closed, '2026-09-20 23:59:59');
        $this->memo($controller, $station, 'OUTSIDE-RANGE', SdmType::Ticketing, MemoStatus::Closed, '2026-09-21 00:00:00');
        $this->memo($controller, $station, 'PREVIOUS-MONTH', SdmType::Ticketing, MemoStatus::Closed, '2026-08-31 23:59:59');

        $this->actingAs($controller)
            ->get(route('dashboard', ['date_from' => '2026-09-10', 'date_to' => '2026-09-20']))
            ->assertOk()
            ->assertViewHas('stationControllerDashboard', fn (array $dashboard): bool => $dashboard['total_sdms'] === 2)
            ->assertDontSee('START-BOUNDARY')
            ->assertDontSee('END-BOUNDARY')
            ->assertDontSee('OUTSIDE-RANGE')
            ->assertDontSee('PREVIOUS-MONTH')
            ->assertSee('value="2026-09-10"', false)
            ->assertSee('value="2026-09-20"', false);
    }

    public function test_service_filter_affects_dashboard_totals_charts_and_records(): void
    {
        [$controller, $station] = $this->stationController();

        $this->memo($controller, $station, 'CFA-ONLY', SdmType::Cfs, MemoStatus::AwaitingStationManager, '2026-09-10 10:00:00');
        $this->memo($controller, $station, 'TVS-HIDDEN', SdmType::Ticketing, MemoStatus::AwaitingStationManager, '2026-09-11 10:00:00');

        $this->actingAs($controller)
            ->get(route('dashboard', ['service_type' => SdmType::Cfs->value]))
            ->assertOk()
            ->assertViewHas('stationControllerDashboard', function (array $dashboard): bool {
                return $dashboard['total_sdms'] === 1
                    && $dashboard['service_counts'][SdmType::Cfs->value] === 1
                    && $dashboard['filters']['service_type'] === SdmType::Cfs->value;
            })
            ->assertDontSee('CFA-ONLY')
            ->assertDontSee('TVS-HIDDEN')
            ->assertSee('value="'.SdmType::Cfs->value.'" selected', false);
    }

    public function test_status_filter_affects_dashboard_totals_charts_and_records(): void
    {
        [$controller, $station] = $this->stationController();

        $this->memo($controller, $station, 'AWAITING-ONLY', SdmType::Cfs, MemoStatus::AwaitingStationManager, '2026-09-10 10:00:00');
        $this->memo($controller, $station, 'LINE-HIDDEN', SdmType::Cfs, MemoStatus::LineReview, '2026-09-11 10:00:00');

        $this->actingAs($controller)
            ->get(route('dashboard', ['status' => MemoStatus::AwaitingStationManager->value]))
            ->assertOk()
            ->assertViewHas('stationControllerDashboard', function (array $dashboard): bool {
                return $dashboard['total_sdms'] === 1
                    && $dashboard['status_counts'][MemoStatus::AwaitingStationManager->value] === 1
                    && $dashboard['status_counts'][MemoStatus::LineReview->value] === 0
                    && $dashboard['filters']['status'] === MemoStatus::AwaitingStationManager->value;
            })
            ->assertDontSee('AWAITING-ONLY')
            ->assertDontSee('LINE-HIDDEN')
            ->assertSee('value="'.MemoStatus::AwaitingStationManager->value.'" selected', false);
    }

    public function test_pending_aging_buckets_include_each_boundary(): void
    {
        [$controller, $station] = $this->stationController();

        foreach ([0, 2, 3, 7, 8, 15, 16] as $days) {
            $date = Carbon::now()->subDays($days)->format('Y-m-d H:i:s');
            $this->memo($controller, $station, 'AGE-'.$days, SdmType::Cfs, MemoStatus::AwaitingStationManager, $date, $date);
        }

        $this->memo($controller, $station, 'CLOSED-IGNORED', SdmType::Cfs, MemoStatus::Closed, '2026-09-01 10:00:00', '2026-09-01 10:00:00');

        $this->actingAs($controller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stationControllerDashboard', fn (array $dashboard): bool => $dashboard['aging_buckets']['counts'] === [2, 2, 2, 1]);
    }

    public function test_pending_aging_only_includes_cases_currently_waiting_for_station_manager(): void
    {
        [$controller, $station] = $this->stationController();

        $this->memo($controller, $station, 'WITH-SM', SdmType::Cfs, MemoStatus::AwaitingStationManager, '2026-09-10 10:00:00', '2026-09-10 10:00:00');
        $this->memo($controller, $station, 'PAST-SM', SdmType::Cfs, MemoStatus::AmCmcReview, '2026-09-10 10:00:00', '2026-09-10 10:00:00');

        $this->actingAs($controller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stationControllerDashboard', fn (array $dashboard): bool => array_sum($dashboard['aging_buckets']['counts']) === 1);
    }

    public function test_aging_filter_limits_recent_records_without_changing_global_totals(): void
    {
        [$controller, $station] = $this->stationController();

        $this->memo($controller, $station, 'RECENT-PENDING', SdmType::Cfs, MemoStatus::AwaitingStationManager, '2026-09-20 10:00:00', '2026-09-20 10:00:00');
        $this->memo($controller, $station, 'OLD-PENDING', SdmType::Cfs, MemoStatus::AwaitingStationManager, '2026-09-01 10:00:00', '2026-09-01 10:00:00');

        $this->actingAs($controller)
            ->get(route('dashboard', ['aging' => 'over_15']))
            ->assertOk()
            ->assertViewHas('stationControllerDashboard', function (array $dashboard): bool {
                return $dashboard['total_sdms'] === 2
                    && count($dashboard['recent_sdms']) === 1;
            })
            ->assertDontSee('OLD-PENDING')
            ->assertDontSee('RECENT-PENDING');
    }

    public function test_dashboard_rejects_invalid_filters(): void
    {
        [$controller] = $this->stationController();

        $this->actingAs($controller)
            ->get(route('dashboard', [
                'date_from' => '2026-09-20',
                'date_to' => '2026-09-10',
                'service_type' => 'not-a-service',
                'status' => 'not-a-status',
                'aging' => 'not-a-bucket',
            ]))
            ->assertSessionHasErrors(['date_to', 'service_type', 'status', 'aging']);
    }

    public function test_station_controller_can_load_only_their_sdms_for_a_selected_chart_bar(): void
    {
        [$controller, $station] = $this->stationController();
        $otherController = User::factory()->create(['station_id' => $station->id]);
        $otherController->assignRole(UserRole::Station->value);

        $selected = $this->memo($controller, $station, 'SELECTED-CFA', SdmType::Cfs, MemoStatus::AwaitingStationManager, '2026-09-10 10:00:00');
        $this->memo($controller, $station, 'OTHER-SERVICE', SdmType::Ticketing, MemoStatus::AwaitingStationManager, '2026-09-11 10:00:00');
        $this->memo($otherController, $station, 'OTHER-USER', SdmType::Cfs, MemoStatus::AwaitingStationManager, '2026-09-12 10:00:00');

        $this->actingAs($controller)
            ->getJson(route('dashboard.chart-sdms', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
                'service_type' => SdmType::Cfs->value,
                'status' => MemoStatus::AwaitingStationManager->value,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'memos')
            ->assertJsonPath('memos.0.id', $selected->id)
            ->assertJsonMissingPath('memos.0.serial_number')
            ->assertJsonPath('memos.0.url', route('sdm-memos.show', $selected));
    }

    public function test_station_controller_chart_drill_down_masks_statuses_after_station_manager(): void
    {
        [$controller, $station] = $this->stationController();

        $processed = $this->memo($controller, $station, 'PAST-SM', SdmType::Cfs, MemoStatus::CmcScrutiny, '2026-09-10 10:00:00');

        $this->actingAs($controller)
            ->getJson(route('dashboard.chart-sdms', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
                'status' => MemoStatus::LineReview->value,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'memos')
            ->assertJsonPath('memos.0.id', $processed->id)
            ->assertJsonPath('memos.0.status', 'Processed by SM');

        $this->actingAs($controller)
            ->get(route('dashboard.chart-sdms', [
                'status' => MemoStatus::CmcScrutiny->value,
            ]))
            ->assertSessionHasErrors('status');
    }

    public function test_non_station_user_does_not_receive_the_station_user_dashboard(): void
    {
        $manager = User::factory()->create(['employee_number' => 'SM-100']);
        $manager->assignRole(UserRole::StationManager->value);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('isStationController', false)
            ->assertDontSee('My SDM Dashboard');
    }

    /** @return array{User, Station} */
    private function stationController(): array
    {
        $line = Line::query()->create([
            'code' => 'BLUE',
            'name' => 'Blue Line',
            'is_active' => true,
        ]);
        $station = Station::query()->create([
            'code' => 'BLUE-01',
            'name' => 'Blue Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
        $controller = User::factory()->create(['station_id' => $station->id]);
        $controller->assignRole(UserRole::Station->value);

        return [$controller, $station];
    }

    private function memo(
        User $creator,
        Station $station,
        string $serialNumber,
        SdmType $serviceType,
        MemoStatus $status,
        string $createdAt,
        ?string $submittedAt = null,
    ): ServiceDeficiencyMemo {
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => $serviceType->value,
            'serial_number' => $serialNumber,
            'station_id' => $station->id,
            'station_name' => $station->name,
            'status' => $status->value,
            'created_by' => $creator->id,
            'submitted_at' => $submittedAt,
        ]);

        $memo->forceFill([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])->saveQuietly();

        return $memo;
    }
}
