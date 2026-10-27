<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ServiceDeficiencyMemoServiceTypeTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([SdmType::Cfs, 'CFA'])]
    #[TestWith([SdmType::Ticketing, 'TVS'])]
    #[TestWith([SdmType::WandW, 'W&W'])]
    #[TestWith([SdmType::Marshall, 'MAR'])]
    public function test_station_controller_submits_each_supported_service_type(SdmType $type, string $serialCode): void
    {
        $this->travelTo('2026-10-27 17:22:00');
        config()->set('sdm.require_contract_workflow', true);
        $this->seed(RolesAndPermissionsSeeder::class);
        $line = Line::query()->create(['code' => 'L-1W', 'name' => 'Red Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'RI', 'name' => 'Rithala', 'line_id' => $line->id, 'is_active' => true]);
        $stationUser = User::factory()->create(['station_id' => $station->id]);
        $stationUser->assignRole(UserRole::Station->value);
        $stationManager = User::factory()->create(['employee_number' => 'SM-SERVICE-TYPE']);
        $stationManager->assignRole(UserRole::StationManager->value);

        foreach ([UserRole::ManagerOperations, UserRole::DeputyGeneralManager, UserRole::CmcTeamLead] as $role) {
            User::factory()->create()->assignRole($role->value);
        }

        $contractor = Contractor::query()->create(['name' => 'Service Type Contractor', 'is_active' => true]);
        $contract = ServiceContract::query()->create([
            'contract_group' => str()->ulid()->toString(),
            'contractor_id' => $contractor->id,
            'line_id' => $line->id,
            'station_id' => $station->id,
            'service_type' => $type->value,
            'contract_number' => 'SERVICE-TYPE/2026/01',
            'title' => 'Service Type Contract',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'status' => 'published',
        ]);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => $type->value,
            'contractor_id' => $contractor->id,
            'staff_name' => 'Operator',
            'employee_number' => 'EMP-SERVICE-TYPE',
            'incident_date' => '2026-10-27',
            'incident_time' => '17:21',
            'station_manager_employee_number' => $stationManager->employee_number,
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::DressCode->value],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame($type, $memo->service_type);
        $this->assertSame('L-1W/RI/'.$serialCode.'/2026/10/01', $memo->serial_number);
        $this->assertSame($contract->id, $memo->service_contract_id);
        $this->assertSame(MemoStatus::AwaitingStationManager, $memo->status);
        $this->assertSame($stationManager->id, $memo->workflowSteps()->where('stage', WorkflowStepType::StationManager->value)->value('assigned_to'));
        $audit = $memo->auditLogs()->where('event', 'memo.submitted')->sole();
        $this->assertSame($stationUser->id, $audit->actor_id);
        $this->assertSame($type->value, $audit->after_values['service_type']);
    }
}
