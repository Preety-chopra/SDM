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

class SdmMemoDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $line = Line::query()->create(['code' => 'DATE-LINE', 'name' => 'Date Line', 'is_active' => true]);
        $this->station = Station::query()->create([
            'code' => 'DATE-01',
            'name' => 'Date Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
    }

    public function test_memo_page_filters_incident_dates_using_an_inclusive_from_and_to_range(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $this->memo('BEFORE-RANGE', '2026-09-09');
        $this->memo('RANGE-START', '2026-09-10');
        $this->memo('RANGE-END', '2026-09-20');
        $this->memo('AFTER-RANGE', '2026-09-21');

        $this->actingAs($administrator)
            ->get(route('sdm-memos.index', [
                'incident_date_from' => '2026-09-10',
                'incident_date_to' => '2026-09-20',
            ]))
            ->assertOk()
            ->assertSee('Incident date from')
            ->assertSee('Incident date to')
            ->assertSee('value="2026-09-10"', false)
            ->assertSee('value="2026-09-20"', false)
            ->assertSee('RANGE-START')
            ->assertSee('RANGE-END')
            ->assertDontSee('BEFORE-RANGE')
            ->assertDontSee('AFTER-RANGE');
    }

    public function test_incident_date_to_cannot_be_before_incident_date_from(): void
    {
        $this->actingAs($this->user(UserRole::Administrator))
            ->get(route('sdm-memos.index', [
                'incident_date_from' => '2026-09-20',
                'incident_date_to' => '2026-09-10',
            ]))
            ->assertSessionHasErrors('incident_date_to');
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function memo(string $serialNumber, string $incidentDate): ServiceDeficiencyMemo
    {
        return ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => $serialNumber,
            'station_id' => $this->station->id,
            'station_name' => $this->station->name,
            'line_code' => 'DATE-LINE',
            'line_name' => 'Date Line',
            'incident_date' => $incidentDate,
            'status' => MemoStatus::AmCmcReview->value,
            'workflow_version' => 2,
            'submitted_at' => now(),
        ]);
    }
}
