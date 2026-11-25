<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
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
        $creator = User::factory()->create(['station_id' => $station->id]);
        $creator->assignRole(UserRole::Station->value);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'SM-TABLE-SUBMITTED',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_code' => $line->code,
            'line_name' => $line->name,
            'status' => MemoStatus::Submitted->value,
            'current_stage' => WorkflowStage::StationManager->value,
            'created_by' => $creator->id,
            'submitted_at' => now(),
        ]);
        $memo->approvals()->create([
            'role' => WorkflowStage::StationManager->value,
            'sequence' => WorkflowStage::StationManager->sequence(),
            'assigned_to' => $stationManager->id,
            'assigned_name' => $stationManager->name,
            'assigned_employee_number' => $stationManager->employee_number,
            'decision' => 'pending',
        ]);

        $response = $this->actingAs($stationManager)
            ->get(route('sdm-memos.index', ['acknowledge_notifications' => 1]));

        $response
            ->assertOk()
            ->assertSee($memo->serial_number)
            ->assertSee('id="sdm-filter-form"', false)
            ->assertSee('form="sdm-filter-form"', false)
            ->assertSee('aria-label="Open SDM type filter"', false)
            ->assertSee('aria-label="Open Line filter"', false)
            ->assertSee('aria-label="Open Station filter"', false)
            ->assertSee('aria-label="Open Incident date filter"', false)
            ->assertSee('aria-label="Open Submitted on filter"', false)
            ->assertSee('aria-label="Open Current status filter"', false)
            ->assertDontSee('id="service_type"', false)
            ->assertDontSee($line->name)
            ->assertDontSee('name="_method" value="DELETE"', false);

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $locationHeaders = '//th[.//summary[@aria-label="Open Line filter"] or .//summary[@aria-label="Open Station filter"]]';
        $this->assertCount(2, $xpath->query($locationHeaders));
        $this->assertSame(
            ['line_code', 'station_id'],
            array_map(fn (\DOMAttr $name): string => $name->value, iterator_to_array($xpath->query($locationHeaders.'//select/@name'))),
        );
        $this->assertSame($line->code, $xpath->evaluate('normalize-space(//tbody/tr/td[4])'));
        $this->assertSame($station->name, $xpath->evaluate('normalize-space(//tbody/tr/td[5])'));

        $this->assertDoesNotMatchRegularExpression('/>\s*Action\s*</', $response->getContent());
    }
}
