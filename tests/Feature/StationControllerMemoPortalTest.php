<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StationControllerMemoPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('sdm.require_contract_workflow', false);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_station_controller_navigation_contains_only_the_three_memo_options(): void
    {
        [$controller] = $this->stationControllerWithHierarchy();

        $this->actingAs($controller)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('data-station-controller-navigation', false)
            ->assertSee(route('sdm-memos.create'), false)
            ->assertSee(route('sdm-memos.index'), false)
            ->assertSee(route('sdm-memos.drafts'), false)
            ->assertSee('Create New SDM')
            ->assertSee('View All')
            ->assertSee('Drafts')
            ->assertDontSee('data-app-sidebar', false)
            ->assertDontSee(route('dashboard'), false)
            ->assertDontSee(route('profile.edit'), false);
    }

    public function test_view_all_lists_only_the_latest_ten_submitted_sdms_created_by_the_station_controller(): void
    {
        [$controller, , , $stationManager] = $this->stationControllerWithHierarchy();
        $otherController = User::factory()->create();
        $otherController->assignRole(UserRole::Station->value);

        $oldest = $this->memo($controller, 'OLDEST-EXCLUDED', MemoStatus::Submitted, now()->subHours(12));
        $this->assignStationManager($oldest, $stationManager, 'pending');

        $recent = collect(range(1, 10))->map(function (int $number) use ($controller, $stationManager): ServiceDeficiencyMemo {
            $status = $number === 1 ? MemoStatus::Submitted : MemoStatus::UnderReview;
            $memo = $this->memo($controller, 'SDM-RECENT-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), $status, now()->subMinutes($number));
            $memo->deficiencies()->create(['type' => DeficiencyType::LateReporting->value]);
            $this->assignStationManager($memo, $stationManager, $status === MemoStatus::Submitted ? 'pending' : 'approved');

            return $memo;
        });

        $draft = $this->memo($controller, 'OWN-DRAFT-HIDDEN', MemoStatus::Draft, null);
        $other = $this->memo($otherController, 'OTHER-CONTROLLER-HIDDEN', MemoStatus::Submitted, now());

        $this->actingAs($controller)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->count() === 10
                && $memos->every(fn (ServiceDeficiencyMemo $memo): bool => $memo->created_by === $controller->id && $memo->submitted_at !== null))
            ->assertSee('SDM serial number')
            ->assertSee('Deficiency')
            ->assertSee('Submitted on')
            ->assertSee('Submitted to')
            ->assertSee('Pending')
            ->assertSee('Processed by SM')
            ->assertSee($stationManager->name)
            ->assertSee($stationManager->employee_number)
            ->assertSee('Late Reporting')
            ->assertSee(route('sdm-memos.show', $recent->first()), false)
            ->assertDontSee($oldest->serial_number)
            ->assertDontSee($draft->serial_number)
            ->assertDontSee($other->serial_number);
    }

    public function test_drafts_page_lists_only_the_station_controllers_own_drafts_with_edit_and_delete_actions(): void
    {
        [$controller] = $this->stationControllerWithHierarchy();
        $otherController = User::factory()->create();
        $otherController->assignRole(UserRole::Station->value);
        $ownDraft = $this->memo($controller, 'OWN-DRAFT', MemoStatus::Draft, null);
        $ownDraft->deficiencies()->create(['type' => DeficiencyType::Other->value, 'remarks' => 'Draft details']);
        $submitted = $this->memo($controller, 'OWN-SUBMITTED', MemoStatus::Submitted, now());
        $otherDraft = $this->memo($otherController, 'OTHER-DRAFT', MemoStatus::Draft, null);

        $this->actingAs($controller)->get(route('sdm-memos.drafts'))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->pluck('id')->all() === [$ownDraft->id])
            ->assertSee(route('sdm-memos.edit', $ownDraft), false)
            ->assertSee(route('sdm-memos.destroy', $ownDraft), false)
            ->assertDontSee($submitted->serial_number)
            ->assertDontSee($otherDraft->serial_number);

        $this->actingAs($controller)->get(route('sdm-memos.edit', $ownDraft))->assertOk();
        $this->actingAs($controller)->get(route('sdm-memos.edit', $otherDraft))->assertForbidden();
    }

    public function test_station_controller_cannot_open_pages_outside_the_memo_portal_or_another_users_memo(): void
    {
        [$controller] = $this->stationControllerWithHierarchy();
        $otherController = User::factory()->create();
        $otherController->assignRole(UserRole::Station->value);
        $otherMemo = $this->memo($otherController, 'OTHER-SDM', MemoStatus::Submitted, now());

        foreach ([
            route('dashboard'),
            route('dashboard.chart-sdms'),
            route('dashboard.line-sdms'),
            route('profile.edit'),
            route('users.create_any'),
        ] as $url) {
            $this->actingAs($controller)->get($url)->assertForbidden();
        }

        $this->actingAs($controller)->get(route('sdm-memos.show', $otherMemo))->assertForbidden();
        $this->actingAs($controller)->get(route('sdm-memos.create'))->assertOk();
        $this->actingAs($controller)->get(route('sdm-memos.index'))->assertOk();
        $this->actingAs($controller)->get(route('sdm-memos.drafts'))->assertOk();
    }

    public function test_station_controller_is_redirected_to_view_all_after_creating_and_submitting_an_sdm(): void
    {
        [$controller, , , $stationManager] = $this->stationControllerWithHierarchy();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->actingAs($controller)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'NEW-SC-SDM',
            'contractor_id' => $contractor->id,
            'incident_date' => '25-09-2026',
            'incident_time' => '10:15',
            'staff_name' => 'Outsourced Staff',
            'employee_number' => 'OUT-100',
            'station_manager_employee_number' => $stationManager->employee_number,
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Test deficiency'],
        ])->assertRedirect(route('sdm-memos.index'));

        $memo = ServiceDeficiencyMemo::query()->where('created_by', $controller->id)->sole();
        $this->assertModelExists($memo);
        $this->assertSame($stationManager->id, $memo->approvals()->where('role', WorkflowStage::StationManager->value)->value('assigned_to'));
        $this->assertDatabaseCount('reporting_relationships', 0);
    }

    public function test_legacy_station_controller_role_receives_the_same_restricted_portal(): void
    {
        [, , $station] = $this->stationControllerWithHierarchy();
        $legacyController = User::factory()->create(['station_id' => $station->id]);
        $legacyRole = Role::findOrCreate('station_controller');
        $legacyRole->syncPermissions(Role::findByName(UserRole::Station->value)->permissions);
        $legacyController->assignRole($legacyRole);

        $this->actingAs($legacyController)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('data-station-controller-navigation', false);
        $this->actingAs($legacyController)->get(route('dashboard'))->assertForbidden();
    }

    /** @return array{User, Line, Station, User} */
    private function stationControllerWithHierarchy(): array
    {
        $line = Line::query()->create(['code' => 'RED', 'name' => 'Red Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'RED-01', 'name' => 'Red Station', 'line_id' => $line->id, 'is_active' => true]);
        $controller = User::factory()->create(['station_id' => $station->id]);
        $controller->assignRole(UserRole::Station->value);
        $stationManager = User::factory()->create(['name' => 'Station Manager One', 'employee_number' => 'SM-100']);
        $stationManager->assignRole(UserRole::StationManager->value);
        $lineManager = User::factory()->create(['employee_number' => 'AM-100']);
        $lineManager->assignRole(UserRole::AssistantManager->value);
        $operationsManager = User::factory()->create();
        $operationsManager->assignRole(UserRole::ManagerOperations->value);
        $dgm = User::factory()->create();
        $dgm->assignRole(UserRole::DeputyGeneralManager->value);

        return [$controller, $line, $station, $stationManager];
    }

    private function memo(User $creator, string $serialNumber, MemoStatus $status, mixed $submittedAt): ServiceDeficiencyMemo
    {
        return ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => $serialNumber,
            'station_id' => $creator->station_id ?? Station::query()->value('id'),
            'station_name' => 'Red Station',
            'line_name' => 'Red Line',
            'line_code' => 'RED',
            'status' => $status->value,
            'workflow_version' => 1,
            'current_stage' => $status === MemoStatus::Submitted ? WorkflowStage::StationManager->value : WorkflowStage::LineManager->value,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            'submitted_at' => $submittedAt,
        ]);
    }

    private function assignStationManager(ServiceDeficiencyMemo $memo, User $stationManager, string $decision): void
    {
        $memo->approvals()->create([
            'role' => WorkflowStage::StationManager->value,
            'sequence' => 1,
            'assigned_to' => $stationManager->id,
            'assigned_name' => $stationManager->name,
            'assigned_employee_number' => $stationManager->employee_number,
            'decision' => $decision,
        ]);
    }
}
