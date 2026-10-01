<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationControllerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_station_controller_dashboard_and_chart_endpoints_are_not_available(): void
    {
        $controller = User::factory()->create();
        $controller->assignRole(UserRole::Station->value);

        foreach ([
            route('dashboard'),
            route('dashboard.chart-sdms'),
            route('dashboard.line-sdms'),
        ] as $url) {
            $this->actingAs($controller)->get($url)->assertForbidden();
        }
    }

    public function test_non_station_controller_user_can_still_access_the_dashboard(): void
    {
        $stationManager = User::factory()->create();
        $stationManager->assignRole(UserRole::StationManager->value);

        $this->actingAs($stationManager)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('isStationController', false);
    }
}
