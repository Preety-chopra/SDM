<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\AftCashCheckReport;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AftCashCheckReportTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function station(): Station
    {
        $line = Line::query()->create(['code' => '7', 'name' => 'Pink Line', 'is_active' => true]);

        return Station::query()->create(['code' => 'VNnR', 'name' => 'Vinobapuri', 'line_id' => $line->id, 'is_active' => true]);
    }

    private function row(Station $station, array $overrides = []): array
    {
        return [
            'check_date' => '2026-10-15', 'station_id' => $station->id, 'check_type' => 'SURP',
            'tom_number' => '81', 'check_time' => '17:01', 'operator_name' => 'SARTAJ AHAMAD',
            'employee_number' => '200165', 'employer' => 'SKM', 'eos_number' => '3721',
            'cash_as_per_eos' => '500.61', 'actual_cash' => '500.61', 'hidden_amount' => '0',
            'cash_deposit_at_station' => '0',
            ...$overrides,
        ];
    }

    private function reviewers(): array
    {
        return [
            'dgm_aft' => $this->user(UserRole::DgmAft),
            'dgm' => $this->user(UserRole::DeputyGeneralManager),
            'am' => $this->user(UserRole::ManagerOperations),
            'sm' => $this->user(UserRole::CmcTeamLead),
            'staff' => $this->user(UserRole::CmcStaff),
        ];
    }

    private function submit(User $aft, Station $station): AftCashCheckReport
    {
        $this->actingAs($aft)->post(route('aft-cash-check-reports.store'), [
            'action' => 'submit', 'entries' => [$this->row($station)],
        ])->assertSessionHasNoErrors()->assertRedirect();

        return AftCashCheckReport::query()->sole();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_aft_can_open_the_digital_format_and_save_an_incomplete_multi_row_draft(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $this->actingAs($aft)->get(route('aft-cash-check-reports.create'))->assertOk()
            ->assertSee('AFT Cash Check Report')->assertSee('Cash as per EOS')
            ->assertDontSee('Previous history')->assertDontSee('name="entries[0][remarks]"', false)->assertSee('Add cash check');
        $this->post(route('aft-cash-check-reports.store'), [
            'action' => 'save', 'entries' => [['station_id' => $station->id, 'tom_number' => '81'], ['tom_number' => 'TVM']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $report = AftCashCheckReport::query()->sole();
        $this->assertSame('draft', $report->status);
        $this->assertCount(2, $report->entries);
        $this->assertNull($report->submitted_at);
        $this->assertDatabaseCount('aft_cash_check_report_steps', 0);
        $this->get(route('aft-cash-check-reports.edit', $report))->assertOk()->assertSee('81');
        $this->assertDatabaseHas('audit_logs', ['event' => 'aft_cash_check.created', 'actor_id' => $aft->id, 'auditable_id' => $report->id]);
    }

    public function test_submission_snapshots_the_format_and_calculates_exact_cash_differences_on_the_server(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $reviewers = $this->reviewers();
        $this->actingAs($aft)->post(route('aft-cash-check-reports.store'), [
            'action' => 'submit', 'entries' => [
                $this->row($station, ['cash_as_per_eos' => '1486.10', 'actual_cash' => '1490.20', 'difference_amount' => 999, 'cash_status' => 'OK']),
                $this->row($station, ['cash_as_per_eos' => '6585', 'actual_cash' => '6581', 'employer' => 'DMRC']),
                $this->row($station),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $report = AftCashCheckReport::query()->sole();
        $this->assertSame('submitted', $report->status);
        $this->assertSame($reviewers['dgm_aft']->id, $report->currentStep->assigned_to);
        $this->assertSame(WorkflowStepType::DgmAftReview, $report->currentStep->stage);
        $this->assertSame(['4.10', '-4.00', '0.00'], $report->entries->pluck('difference_amount')->all());
        $this->assertSame(['EXCESS', 'SHORT', 'OK'], $report->entries->pluck('cash_status')->all());
        $this->assertSame('7', $report->entries->first()->line_code);
        $this->assertSame('VNnR', $report->entries->first()->station_code);
        $this->assertNull($report->entries->first()->penalty_amount);
        $this->assertNull($report->entries->first()->cumulative_negative_points);
        $this->assertNull($report->entries->first()->remarks);
        $this->assertDatabaseCount('service_deficiency_memos', 0);
        $this->actingAs($reviewers['dgm_aft'])->get(route('aft-cash-check-reports.index', ['pending' => 1]))
            ->assertOk()->assertSee($report->report_number);
        $this->get(route('aft-cash-check-reports.show', $report))->assertOk()
            ->assertSee('SARTAJ AHAMAD')->assertSee('EXCESS')->assertSee('SHORT');
        $this->assertDatabaseHas('audit_logs', ['event' => 'aft_cash_check.submitted', 'auditable_id' => $report->id, 'actor_id' => $aft->id]);
    }

    public function test_only_aft_can_create_and_only_the_owner_can_edit_a_draft(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $this->actingAs($aft)->post(route('aft-cash-check-reports.store'), ['action' => 'save', 'entries' => [$this->row($station)]])
            ->assertSessionHasNoErrors();
        $report = AftCashCheckReport::query()->sole();
        $this->actingAs($this->user(UserRole::Aft))->get(route('aft-cash-check-reports.show', $report))->assertForbidden();
        $this->put(route('aft-cash-check-reports.update', $report), ['action' => 'save', 'entries' => []])->assertForbidden();
        foreach ([UserRole::Station, UserRole::StationManager, UserRole::DgmAft, UserRole::CmcStaff] as $role) {
            $this->actingAs($this->user($role))->get(route('aft-cash-check-reports.create'))->assertForbidden();
        }
        $this->actingAs($aft)->put(route('aft-cash-check-reports.update', $report), ['action' => 'save', 'entries' => [$this->row($station, ['operator_name' => 'Updated Operator'])]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Updated Operator', $report->fresh()->entries->sole()->operator_name);
        $this->assertDatabaseHas('audit_logs', ['event' => 'aft_cash_check.updated', 'actor_id' => $aft->id]);
    }

    #[TestWith(['actual_cash', '-1'])]
    #[TestWith(['actual_cash', '12.345'])]
    #[TestWith(['cash_as_per_eos', 'not cash'])]
    #[TestWith(['check_time', '25:00'])]
    #[TestWith(['operator_name', ''])]
    #[TestWith(['employee_number', ''])]
    public function test_invalid_row_cannot_be_submitted(string $field, mixed $value): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $this->actingAs($aft)->post(route('aft-cash-check-reports.store'), [
            'action' => 'submit', 'entries' => [$this->row($station, [$field => $value])],
        ])->assertSessionHasErrors('entries.0.'.$field);
        $this->assertDatabaseCount('aft_cash_check_reports', 0);
    }

    public function test_submission_requires_an_enabled_unambiguous_review_chain_and_active_station_line(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $this->actingAs($aft)->post(route('aft-cash-check-reports.store'), ['action' => 'submit', 'entries' => [$this->row($station)]])
            ->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('aft_cash_check_reports', 0);
        $this->reviewers();
        $station->metroLine->update(['is_active' => false]);
        $this->post(route('aft-cash-check-reports.store'), ['action' => 'submit', 'entries' => [$this->row($station)]])
            ->assertSessionHasErrors('entries.0.station_id');
    }

    public function test_submitted_report_is_locked_cannot_be_duplicated_or_actioned_out_of_turn(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $reviewers = $this->reviewers();
        $report = $this->submit($aft, $station);
        $this->actingAs($aft)->put(route('aft-cash-check-reports.update', $report), ['action' => 'save', 'entries' => [$this->row($station)]])->assertForbidden();
        $this->post(route('aft-cash-check-reports.submit', $report))->assertForbidden();
        $this->actingAs($reviewers['dgm'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward'])->assertForbidden();
        $this->actingAs($reviewers['dgm_aft'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'approve'])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('aft_cash_check_reports', 1);
        $this->assertDatabaseCount('aft_cash_check_report_steps', 8);
    }

    #[TestWith(['remarks', 'Entered by AFT'])]
    #[TestWith(['cumulative_negative_points', 5])]
    #[TestWith(['penalty_amount', '100'])]
    public function test_aft_cannot_fill_cmc_review_fields(string $field, mixed $value): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $this->actingAs($aft)->post(route('aft-cash-check-reports.store'), [
            'action' => 'save', 'entries' => [$this->row($station, [$field => $value])],
        ])->assertSessionHasErrors('entries.0.'.$field);
        $this->assertDatabaseCount('aft_cash_check_reports', 0);
    }

    public function test_cash_report_follows_the_aft_review_chain_without_changing_sdm_workflows(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $users = $this->reviewers();
        $report = $this->submit($aft, $station);
        foreach (['dgm_aft', 'dgm', 'am'] as $role) {
            $this->actingAs($users[$role])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward'])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->actingAs($users['sm'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'assign', 'cmc_staff_id' => $users['staff']->id])->assertSessionHasNoErrors()->assertRedirect();
        foreach (['staff', 'sm', 'am'] as $role) {
            $this->actingAs($users[$role])->post(route('aft-cash-check-reports.workflow', $report), [
                'action' => 'forward',
                ...($role === 'staff' ? ['reviews' => [[
                    'entry_id' => $report->entries->sole()->id, 'remarks' => 'Checked by CMC staff.',
                    'cumulative_negative_points' => 5, 'penalty_amount' => '100.50',
                ]]] : []),
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->actingAs($users['dgm'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'approve'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $report->fresh()->status);
        $this->assertNull($report->fresh()->current_step_id);
        $this->assertDatabaseCount('service_deficiency_memos', 0);
        $this->assertSame('100.50', $report->fresh()->entries->sole()->penalty_amount);
        $this->assertSame('Checked by CMC staff.', $report->fresh()->entries->sole()->remarks);
        $this->assertDatabaseHas('audit_logs', ['event' => 'aft_cash_check.workflow.approve', 'actor_id' => $users['dgm']->id]);
    }

    public function test_cmc_review_is_required_for_every_row_and_only_cmc_staff_can_enter_it(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $users = $this->reviewers();
        $report = $this->submit($aft, $station);
        $review = ['entry_id' => $report->entries->sole()->id, 'remarks' => '<script>alert(1)</script>', 'cumulative_negative_points' => 2, 'penalty_amount' => '10.20'];
        $this->actingAs($users['dgm_aft'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward', 'reviews' => [$review]])
            ->assertSessionHasErrors('reviews');
        foreach (['dgm_aft', 'dgm', 'am'] as $role) {
            $this->actingAs($users[$role])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward'])->assertSessionHasNoErrors();
        }
        $this->actingAs($users['sm'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'assign', 'cmc_staff_id' => $users['dgm']->id])
            ->assertSessionHasErrors('cmc_staff_id');
        $this->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'assign', 'cmc_staff_id' => $users['staff']->id])->assertSessionHasNoErrors();
        $this->actingAs($users['staff'])->get(route('aft-cash-check-reports.show', $report))->assertOk()->assertSee('name="reviews[0][penalty_amount]"', false);
        $this->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward'])->assertSessionHasErrors('reviews');
        $this->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward', 'reviews' => [[...$review, 'entry_id' => 99999]]])->assertSessionHasErrors('reviews.0.entry_id');
        $this->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward', 'reviews' => [[...$review, 'penalty_amount' => '-1', 'cumulative_negative_points' => -1]]])
            ->assertSessionHasErrors(['reviews.0.penalty_amount', 'reviews.0.cumulative_negative_points']);
        $this->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward', 'reviews' => [$review]])->assertSessionHasNoErrors();
        $this->actingAs($aft)->get(route('aft-cash-check-reports.show', $report))->assertOk()->assertSee(e($review['remarks']), false)->assertDontSee($review['remarks'], false);
        $this->assertDatabaseHas('audit_logs', ['event' => 'aft_cash_check.entry.reviewed', 'actor_id' => $users['staff']->id]);
    }

    public function test_a_pending_cash_check_reviewer_cannot_be_disabled_and_used_stations_cannot_be_deleted(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $users = $this->reviewers();
        $this->submit($aft, $station);
        $admin = $this->user(UserRole::Administrator);
        $this->actingAs($admin)->patch(route('users.status', $users['dgm_aft']), ['is_enabled' => false])->assertSessionHasErrors('user');
        $this->assertTrue($users['dgm_aft']->fresh()->is_enabled);
        $this->delete(route('stations.destroy', $station))->assertSessionHasErrors('station');
        $this->assertDatabaseHas('stations', ['id' => $station->id]);
    }

    public function test_new_cash_check_tables_can_be_rolled_back_and_recreated(): void
    {
        $migration = require database_path('migrations/2026_11_10_124214_create_aft_cash_check_report_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('aft_cash_check_reports'));
        $migration->up();
        $this->assertDatabaseCount('aft_cash_check_reports', 0);
        $this->assertDatabaseCount('aft_cash_check_entries', 0);
        $this->assertDatabaseCount('aft_cash_check_report_steps', 0);
    }

    public function test_saved_draft_can_be_completed_and_submitted_once(): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $this->reviewers();
        $this->actingAs($aft)->post(route('aft-cash-check-reports.store'), ['action' => 'save', 'entries' => [['tom_number' => 'TVM']]])->assertSessionHasNoErrors();
        $report = AftCashCheckReport::query()->sole();
        $number = $report->report_number;
        $this->post(route('aft-cash-check-reports.submit', $report), ['entries' => [$this->row($station)]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($number, $report->fresh()->report_number);
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertDatabaseCount('aft_cash_check_reports', 1);
        $this->assertDatabaseCount('aft_cash_check_entries', 1);
    }

    #[TestWith(['sm', 'return', 'cmc_assessment'])]
    #[TestWith(['am', 'return', 'cmc_verification'])]
    #[TestWith(['dgm', 'return', 'cmc_assessment'])]
    #[TestWith(['dgm', 'cancel', null])]
    public function test_review_returns_preserve_inspection_data_and_cancellation_is_audited(string $reviewer, string $action, ?string $expectedStage): void
    {
        $aft = $this->user(UserRole::Aft);
        $station = $this->station();
        $users = $this->reviewers();
        $report = $this->submit($aft, $station);
        foreach (['dgm_aft', 'dgm', 'am'] as $role) {
            $this->actingAs($users[$role])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward'])->assertSessionHasNoErrors();
        }
        $this->actingAs($users['sm'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'assign', 'cmc_staff_id' => $users['staff']->id])->assertSessionHasNoErrors();
        $this->actingAs($users['staff'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward', 'reviews' => [[
            'entry_id' => $report->entries->sole()->id, 'remarks' => 'Initial CMC review.', 'cumulative_negative_points' => 2, 'penalty_amount' => '10.00',
        ]]])->assertSessionHasNoErrors();
        if ($reviewer !== 'sm') {
            $this->actingAs($users['sm'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward'])->assertSessionHasNoErrors();
        }
        if ($reviewer === 'dgm') {
            $this->actingAs($users['am'])->post(route('aft-cash-check-reports.workflow', $report), ['action' => 'forward'])->assertSessionHasNoErrors();
        }
        $this->actingAs($users[$reviewer])->post(route('aft-cash-check-reports.workflow', $report), ['action' => $action])->assertSessionHasErrors('remarks');
        $this->post(route('aft-cash-check-reports.workflow', $report), ['action' => $action, 'remarks' => 'Check the supporting record again.'])->assertSessionHasNoErrors();
        $this->assertSame($expectedStage, $report->fresh()->currentStep?->stage->value);
        $this->assertSame('500.61', $report->fresh()->entries->sole()->actual_cash);
        $this->assertSame('Initial CMC review.', $report->fresh()->entries->sole()->remarks);
        $this->assertDatabaseHas('audit_logs', ['event' => 'aft_cash_check.workflow.'.$action, 'actor_id' => $users[$reviewer]->id]);
        if ($action === 'cancel') {
            $this->assertSame('cancelled', $report->fresh()->status);
        } else {
            $this->assertSame(2, $report->fresh()->currentStep->round);
        }
    }
}
