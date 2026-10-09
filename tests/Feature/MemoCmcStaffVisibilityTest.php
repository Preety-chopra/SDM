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

class MemoCmcStaffVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_cmc_workflow_roles_can_see_memo_cmc_staff_details(): void
    {
        $line = Line::query()->create(['code' => 'CMC-VIS', 'name' => 'CMC Visibility Line', 'is_active' => true]);
        $station = Station::query()->create([
            'code' => 'CMC-VIS-01',
            'name' => 'CMC Visibility Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
        $creator = $this->user(UserRole::Station, ['station_id' => $station->id]);
        $assignedCmcStaff = $this->user(UserRole::CmcStaff, ['name' => 'Visible Only To CMC Roles']);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CMC-STAFF-VISIBILITY',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_code' => $line->code,
            'line_name' => $line->name,
            'cmc_staff_id' => $assignedCmcStaff->id,
            'status' => MemoStatus::AmCmcReview->value,
            'workflow_version' => 2,
            'created_by' => $creator->id,
            'submitted_at' => now(),
        ]);

        foreach ([
            UserRole::ManagerOperations,
            UserRole::CmcTeamLead,
            UserRole::CmcStaff,
            UserRole::DeputyGeneralManager,
        ] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('sdm-memos.show', $memo))
                ->assertOk()
                ->assertSee('Memo CMC Staff')
                ->assertSee($assignedCmcStaff->name);
        }

        foreach ([
            UserRole::Administrator,
            UserRole::StationManager,
            UserRole::AssistantManager,
        ] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('sdm-memos.show', $memo))
                ->assertOk()
                ->assertDontSee('Memo CMC Staff')
                ->assertDontSee($assignedCmcStaff->name);
        }

        $this->actingAs($creator)
            ->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertDontSee('Memo CMC Staff')
            ->assertDontSee($assignedCmcStaff->name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }
}
