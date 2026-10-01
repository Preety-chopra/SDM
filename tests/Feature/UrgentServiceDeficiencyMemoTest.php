<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\Line;
use App\Models\MemoApproval;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrgentServiceDeficiencyMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_station_controller_can_mark_a_memo_as_urgent(): void
    {
        [$controller, $station] = $this->stationController();

        $this->actingAs($controller)
            ->withSession(['auth.station_id' => $station->id])
            ->post(route('sdm-memos.store'), [
                'service_type' => SdmType::Cfs->value,
                'is_urgent' => '1',
            ])
            ->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->whereBelongsTo($controller, 'creator')->sole();

        $this->assertTrue($memo->is_urgent);
    }

    public function test_memo_form_offers_an_urgent_flag(): void
    {
        [$controller, $station] = $this->stationController();

        $this->actingAs($controller)
            ->withSession(['auth.station_id' => $station->id])
            ->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSeeInOrder(['Memo details', 'Mark as urgent', 'SDM type'])
            ->assertSee('sm:justify-between', false)
            ->assertSee('Mark as urgent')
            ->assertSee('name="is_urgent"', false)
            ->assertSee('&quot;name&quot;:&quot;is_urgent&quot;', false)
            ->assertSee('&quot;emptyValue&quot;:&quot;Normal&quot;', false);
    }

    public function test_urgent_flag_is_displayed_with_the_sdm_serial_number(): void
    {
        [$controller, $station] = $this->stationController();
        $memo = $this->urgentMemo($controller, $station);

        $this->actingAs($controller)
            ->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee($memo->serial_number)
            ->assertSee('Urgent')
            ->assertSee('animate-pulse', false);
    }

    public function test_assignee_sees_urgent_attention_modal_immediately_after_login(): void
    {
        [$controller, $station] = $this->stationController();
        $stationManager = User::factory()->create();
        $stationManager->assignRole(UserRole::StationManager->value);
        $memo = $this->urgentMemo($controller, $station);

        MemoApproval::query()->create([
            'memo_id' => $memo->id,
            'role' => WorkflowStage::StationManager->value,
            'sequence' => WorkflowStage::StationManager->sequence(),
            'assigned_to' => $stationManager->id,
            'assigned_name' => $stationManager->name,
            'assigned_employee_number' => $stationManager->employee_number,
            'decision' => 'pending',
        ]);

        $this->followingRedirects()
            ->post(route('login'), [
                'username' => $stationManager->name,
                'password' => 'password',
                'station_id' => $station->id,
            ])
            ->assertOk()
            ->assertSee('Urgent attention required')
            ->assertSee($memo->serial_number)
            ->assertSee(route('sdm-memos.show', $memo), false);
    }

    public function test_login_modal_excludes_urgent_memos_not_awaiting_the_user(): void
    {
        [$controller, $station] = $this->stationController();
        $stationManager = User::factory()->create();
        $stationManager->assignRole(UserRole::StationManager->value);
        $memo = $this->urgentMemo($controller, $station);

        MemoApproval::query()->create([
            'memo_id' => $memo->id,
            'role' => WorkflowStage::StationManager->value,
            'sequence' => WorkflowStage::StationManager->sequence(),
            'assigned_to' => User::factory()->create()->id,
            'assigned_name' => 'Another manager',
            'decision' => 'pending',
        ]);

        $this->followingRedirects()
            ->post(route('login'), [
                'username' => $stationManager->name,
                'password' => 'password',
                'station_id' => $station->id,
            ])
            ->assertOk()
            ->assertDontSee('Urgent attention required');
    }

    public function test_login_modal_excludes_non_urgent_memos_awaiting_the_user(): void
    {
        [$controller, $station] = $this->stationController();
        $stationManager = User::factory()->create();
        $stationManager->assignRole(UserRole::StationManager->value);
        $memo = $this->urgentMemo($controller, $station);
        $memo->update(['is_urgent' => false]);

        MemoApproval::query()->create([
            'memo_id' => $memo->id,
            'role' => WorkflowStage::StationManager->value,
            'sequence' => WorkflowStage::StationManager->sequence(),
            'assigned_to' => $stationManager->id,
            'assigned_name' => $stationManager->name,
            'assigned_employee_number' => $stationManager->employee_number,
            'decision' => 'pending',
        ]);

        $this->followingRedirects()
            ->post(route('login'), [
                'username' => $stationManager->name,
                'password' => 'password',
                'station_id' => $station->id,
            ])
            ->assertOk()
            ->assertDontSee('Urgent attention required');
    }

    /** @return array{User, Station} */
    private function stationController(): array
    {
        $line = Line::query()->create([
            'code' => 'URGENT-LINE',
            'name' => 'Urgent Line',
            'is_active' => true,
        ]);
        $station = Station::query()->create([
            'code' => 'URGENT-01',
            'name' => 'Urgent Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
        $controller = User::factory()->create(['station_id' => $station->id]);
        $controller->assignRole(UserRole::Station->value);

        return [$controller, $station];
    }

    private function urgentMemo(User $controller, Station $station): ServiceDeficiencyMemo
    {
        return ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'RED/URG-01/CFS/2026/09/01',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_name' => $station->metroLine->name,
            'line_code' => $station->metroLine->code,
            'status' => MemoStatus::Submitted->value,
            'current_stage' => WorkflowStage::StationManager->value,
            'created_by' => $controller->id,
            'updated_by' => $controller->id,
            'submitted_at' => now(),
            'is_urgent' => true,
        ]);
    }
}
