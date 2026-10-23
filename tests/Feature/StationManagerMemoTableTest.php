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
use Tests\TestCase;

class StationManagerMemoTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_manager_notification_list_uses_header_filters_line_codes_and_no_action_column(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $line = Line::query()->create([
            'code' => 'SM-LINE-CODE',
            'name' => 'Station Manager Line Name',
            'is_active' => true,
        ]);
        $station = Station::query()->create([
            'code' => 'SM-01',
            'name' => 'Station Manager Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
        $stationManager = User::factory()->create([
            'employee_number' => 'SM-TABLE-01',
            'station_id' => $station->id,
        ]);
        $stationManager->assignRole(UserRole::StationManager->value);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'SM-TABLE-DRAFT',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_code' => $line->code,
            'line_name' => $line->name,
            'status' => MemoStatus::Draft->value,
            'created_by' => $stationManager->id,
        ]);

        $response = $this->actingAs($stationManager)
            ->get(route('sdm-memos.index', ['acknowledge_notifications' => 1]));

        $response
            ->assertOk()
            ->assertSee($memo->serial_number)
            ->assertSee('id="sdm-filter-form"', false)
            ->assertSee('form="sdm-filter-form"', false)
            ->assertSee('aria-label="Open SDM type filter"', false)
            ->assertSee('aria-label="Open Line / station filter"', false)
            ->assertSee('aria-label="Open Incident date filter"', false)
            ->assertSee('aria-label="Open Submitted on filter"', false)
            ->assertSee('aria-label="Open Current status filter"', false)
            ->assertDontSee('id="service_type"', false)
            ->assertSee($line->code)
            ->assertDontSee($line->name)
            ->assertDontSee('name="_method" value="DELETE"', false);

        $this->assertDoesNotMatchRegularExpression('/>\s*Action\s*</', $response->getContent());
    }
}
