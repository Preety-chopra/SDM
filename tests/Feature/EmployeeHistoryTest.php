<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\Contractor;
use App\Models\HistoricalSdm;
use App\Models\HistoricalSdmImport;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public static function roles(): array
    {
        return array_map(fn (UserRole $role): array => [$role, in_array($role, [
            UserRole::ManagerOperations, UserRole::CmcTeamLead,
            UserRole::CmcStaff, UserRole::DeputyGeneralManager,
        ], true)], UserRole::cases());
    }

    #[DataProvider('roles')]
    public function test_only_the_four_cmc_roles_can_access_employee_history(UserRole $role, bool $allowed): void
    {
        $viewer = $this->user($role);

        $this->assertSame($allowed, $viewer->can('viewEmployeeHistory', ServiceDeficiencyMemo::class));
    }

    public function test_history_requires_authentication_and_rejects_other_roles_at_both_urls(): void
    {
        $memo = $this->memo();
        $this->get('/employee-history')->assertRedirect(route('login'));
        $this->get('/sdm-memos/'.$memo->id.'/employee-history')->assertRedirect(route('login'));

        $this->actingAs($this->user(UserRole::Administrator));
        $this->get('/employee-history')->assertForbidden();
        $this->get('/sdm-memos/'.$memo->id.'/employee-history')->assertForbidden();
    }

    public function test_disabled_cmc_members_cannot_access_history(): void
    {
        $viewer = $this->user(UserRole::CmcStaff);
        $viewer->update(['is_enabled' => false]);

        $this->assertFalse($viewer->can('viewEmployeeHistory', ServiceDeficiencyMemo::class));
        $this->actingAs($viewer)->get('/employee-history')->assertRedirect(route('login'));
    }

    public function test_cmc_members_get_history_links_on_the_sdm_view_and_navigation(): void
    {
        $memo = $this->memo();

        $this->actingAs($this->user(UserRole::CmcStaff))
            ->get(route('sdm-memos.show', $memo))->assertOk()
            ->assertSee('View employee history')
            ->assertSee(route('sdm-memos.employee-history', $memo), false)
            ->assertSee(route('employee-history.index'), false);

        $this->actingAs($this->user(UserRole::AssistantManager))
            ->get(route('sdm-memos.show', $memo))->assertOk()
            ->assertDontSee('View employee history')
            ->assertDontSee('Previous approved cases for this staff member')
            ->assertDontSee('href="'.route('employee-history.index').'"', false);
    }

    public function test_search_shows_live_and_imported_records_for_the_exact_employee_and_contractor(): void
    {
        $contractor = Contractor::factory()->create();
        $older = $this->memo(['contractor_id' => $contractor->id, 'serial_number' => 'OLDER-SDM', 'incident_date' => '2026-02-01']);
        $newer = $this->memo(['contractor_id' => $contractor->id, 'serial_number' => 'NEWER-SDM', 'incident_date' => '2026-03-01', 'status' => MemoStatus::Cancelled]);
        $this->memo(['contractor_id' => $contractor->id, 'serial_number' => 'PRIVATE-DRAFT', 'status' => MemoStatus::Draft]);
        $this->memo(['serial_number' => 'OTHER-CONTRACTOR']);
        $this->memo(['contractor_id' => $contractor->id, 'serial_number' => 'OTHER-EMPLOYEE', 'employee_number' => '9001']);
        $history = $this->historical($contractor, ['memo_number' => 'IMPORTED-SDM', 'discrepancies' => '<script>history()</script>', 'aadhaar_number' => '123456789012']);
        $this->historical(Contractor::factory()->create(), ['memo_number' => 'UNRELATED-IMPORT']);
        $this->historical($contractor, ['memo_number' => 'OTHER-IMPORTED-EMPLOYEE', 'employee_number' => '9001']);

        $response = $this->actingAs($this->user(UserRole::CmcStaff))->get('/employee-history?'.http_build_query([
            'contractor_id' => $contractor->id, 'employee_number' => '009001',
        ]))->assertOk()->assertSeeInOrder(['NEWER-SDM', 'OLDER-SDM'])
            ->assertSee('IMPORTED-SDM')->assertSee('Cancelled')
            ->assertSee('<script>history()</script>')->assertDontSee('<script>history()</script>', false)
            ->assertDontSee('123456789012')->assertDontSee('PRIVATE-DRAFT')
            ->assertDontSee('OTHER-CONTRACTOR')->assertDontSee('OTHER-EMPLOYEE')
            ->assertDontSee('UNRELATED-IMPORT')->assertDontSee('OTHER-IMPORTED-EMPLOYEE');

        $this->assertSame([$newer->id, $older->id], $response->viewData('memos')->pluck('id')->all());
        $this->assertSame([$history->id], $response->viewData('historicalRecords')->pluck('id')->all());
    }

    public function test_sdm_history_uses_the_memo_identity_excludes_the_current_memo_and_ignores_query_overrides(): void
    {
        $memo = $this->memo(['serial_number' => 'CURRENT-SDM']);
        $prior = $this->memo(['contractor_id' => $memo->contractor_id, 'serial_number' => 'PRIOR-SDM']);
        $other = $this->memo(['serial_number' => 'UNRELATED-SDM']);

        $response = $this->actingAs($this->user(UserRole::ManagerOperations))
            ->get('/sdm-memos/'.$memo->id.'/employee-history?contractor_id='.$other->contractor_id.'&employee_number=9001')
            ->assertOk()->assertSee('PRIOR-SDM')->assertDontSee('UNRELATED-SDM');

        $this->assertSame([$prior->id], $response->viewData('memos')->pluck('id')->all());
    }

    public function test_sdm_history_cannot_bypass_access_to_a_draft(): void
    {
        $memo = $this->memo(['status' => MemoStatus::Draft]);

        $this->actingAs($this->user(UserRole::CmcTeamLead))
            ->get('/sdm-memos/'.$memo->id.'/employee-history')->assertForbidden();
    }

    public function test_missing_identity_and_no_matching_records_have_clear_empty_states(): void
    {
        $this->actingAs($this->user(UserRole::DeputyGeneralManager));
        $this->get('/employee-history')->assertOk()->assertSee('Select a contractor and enter an employee number');
        $memo = $this->memo(['employee_number' => null]);
        $this->get('/sdm-memos/'.$memo->id.'/employee-history')->assertOk()
            ->assertSee('This SDM has no contractor or employee number');
        $this->get('/employee-history?'.http_build_query(['contractor_id' => $memo->contractor_id, 'employee_number' => 'ABSENT']))
            ->assertOk()->assertSee('No SDM records found')->assertSee('No imported records found');
    }

    public function test_search_requires_both_identity_fields_and_validates_contractor_and_employee_number(): void
    {
        $this->actingAs($this->user(UserRole::CmcStaff));
        $this->get('/employee-history?employee_number=009001')->assertSessionHasErrors('contractor_id');
        $contractor = Contractor::factory()->create();
        $this->get('/employee-history?contractor_id='.$contractor->id)->assertSessionHasErrors('employee_number');
        $this->get('/employee-history?contractor_id=999999&employee_number=009001')->assertSessionHasErrors('contractor_id');
        $this->get('/employee-history?contractor_id='.$contractor->id.'&employee_number='.str_repeat('a', 101))->assertSessionHasErrors('employee_number');
    }

    public function test_history_is_paginated_with_search_filters_preserved(): void
    {
        $contractor = Contractor::factory()->create();
        for ($i = 1; $i <= 21; $i++) {
            $this->memo(['contractor_id' => $contractor->id, 'serial_number' => 'PAGED-'.$i]);
        }

        $response = $this->actingAs($this->user(UserRole::CmcStaff))->get('/employee-history?'.http_build_query([
            'contractor_id' => $contractor->id, 'employee_number' => '009001', 'memos_page' => 2,
        ]))->assertOk()->assertSee('PAGED-1')->assertDontSee('PAGED-21');

        $this->assertSame(21, $response->viewData('memos')->total());
        $this->assertCount(1, $response->viewData('memos'));
        $this->assertStringContainsString('employee_number=009001', $response->viewData('memos')->url(1));
    }

    public function test_history_displays_the_latest_approved_assessment_and_labels_unapproved_proposals(): void
    {
        $memo = $this->memo(['serial_number' => 'ASSESSED-SDM']);
        $attributes = [
            'round' => 1, 'version' => 1, 'source_stage' => 'cmc_assessment', 'proposed_by' => $memo->created_by,
            'bill_month' => '2026-01-01', 'monthly_bill_value' => 100000,
            'proposed_total' => 1200, 'negative_points' => 5, 'staff_action' => 'warning', 'status' => 'approved',
        ];
        $memo->assessments()->create($attributes);
        $memo->assessments()->create([...$attributes, 'version' => 2, 'proposed_total' => 2400, 'negative_points' => 10]);
        $memo->assessments()->create([...$attributes, 'version' => 3, 'proposed_total' => 9999, 'status' => 'proposed']);
        $proposal = $this->memo(['contractor_id' => $memo->contractor_id, 'serial_number' => 'PROPOSED-SDM']);
        $proposal->assessments()->create([...$attributes, 'proposed_by' => $proposal->created_by, 'proposed_total' => 3500, 'status' => 'proposed']);

        $this->actingAs($this->user(UserRole::CmcStaff))->get('/employee-history?'.http_build_query([
            'contractor_id' => $memo->contractor_id, 'employee_number' => '009001',
        ]))->assertOk()->assertSee('2,400.00')->assertSee('Approved')->assertSee('10 negative points')
            ->assertSee('Warning')->assertSee('3,500.00')->assertSee('Proposed')
            ->assertDontSee('9,999.00')->assertDontSee('1,200.00');
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function memo(array $attributes = []): ServiceDeficiencyMemo
    {
        $line = Line::query()->firstOrCreate(['code' => 'HISTORY'], ['name' => 'History Line']);
        $station = Station::query()->firstOrCreate(['code' => 'HISTORY'], ['name' => 'History Station', 'line_id' => $line->id]);

        return ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs, 'station_id' => $station->id, 'station_name' => 'History Station',
            'contractor_id' => $attributes['contractor_id'] ?? Contractor::factory()->create()->id,
            'staff_name' => 'History Employee', 'employee_number' => '009001',
            'incident_date' => '2026-01-01', 'status' => MemoStatus::Closed,
            'created_by' => User::factory()->create()->id, 'workflow_version' => 2,
            ...$attributes,
        ]);
    }

    private function historical(Contractor $contractor, array $attributes = []): HistoricalSdm
    {
        $import = HistoricalSdmImport::query()->create([
            'contractor_id' => $contractor->id, 'source_file' => 'history.xlsx',
            'source_sha256' => hash('sha256', uniqid()), 'source_sheet' => 'SDMs',
            'row_count' => 1, 'summary' => [],
        ]);

        return HistoricalSdm::query()->create([
            'import_id' => $import->id, 'contractor_id' => $contractor->id,
            'source_row' => 1, 'employee_number' => '009001', 'employee_name' => 'History Employee',
            'incident_date' => '2025-01-01', 'contractor_penalty' => '1200.00',
            'negative_points' => '5.00', 'recommended_action' => 'Warning', 'import_issues' => [],
            ...$attributes,
        ]);
    }
}
